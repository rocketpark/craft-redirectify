<?php

namespace rocketpark\redirectify\services;

use Craft;
use craft\base\Component;
use craft\db\Query;
use craft\helpers\Db;
use craft\helpers\DateTimeHelper;
use yii\web\Response;
use rocketpark\redirectify\Plugin;
use rocketpark\redirectify\models\Redirect;
use rocketpark\redirectify\models\Stats;
use rocketpark\redirectify\records\RedirectRecord;
use rocketpark\redirectify\records\StatsRecord;

/**
 * Redirect Service
 *
 * Handles all redirect and 404 tracking logic
 */
class RedirectService extends Component
{
    /**
     * Create a redirect when an entry's URI changes
     *
     * @param \craft\elements\Entry $entry
     */
    public function createRedirectFromEntryUriChange($entry): void
    {
        $oldUri = $entry->getOldAttribute('uri');
        $newUri = $entry->uri;
        
        // Don't create redirect if URIs are the same or if either is null
        if (!$oldUri || !$newUri || $oldUri === $newUri) {
            return;
        }
        
        // Build full URLs
        $oldUrl = '/' . ltrim($oldUri, '/');
        $newUrl = '/' . ltrim($newUri, '/');
        
        // Check if a redirect already exists for this source URL
        $existing = RedirectRecord::find()
            ->where(['sourceUrl' => $oldUrl, 'siteId' => $entry->siteId])
            ->one();
        
        if ($existing) {
            // Update existing redirect to point to new URL
            $existing->destinationUrl = $newUrl;
            $existing->save();
        } else {
            // Create new redirect
            $redirect = new Redirect();
            $redirect->siteId = $entry->siteId;
            $redirect->sourceUrl = $oldUrl;
            $redirect->destinationUrl = $newUrl;
            $redirect->matchType = 'exact';
            $redirect->statusCode = 301;
            $redirect->enabled = true;
            
            $this->saveRedirect($redirect);
        }
    }

    /**
     * Handle a 404 not found exception
     * Check for matching redirect and perform it, or log the 404
     */
    public function handleNotFoundException(): void
    {
        $request = Craft::$app->getRequest();
        
        // Don't handle console requests or CP requests
        if ($request->getIsConsoleRequest() || $request->getIsCpRequest()) {
            return;
        }

        $requestedUrl = $request->getUrl();
        $settings = Plugin::$plugin->getSettings();

        // Strip query string if setting is enabled
        if ($settings->stripQueryStrings) {
            $requestedUrl = strtok($requestedUrl, '?');
        }

        // Try to find a matching redirect
        $redirect = $this->findMatchingRedirect($requestedUrl);

        if ($redirect) {
            $this->performRedirect($redirect, $requestedUrl);
        } else {
            // Log the 404 if statistics tracking is enabled
            if ($settings->trackStatistics) {
                $this->log404($requestedUrl);
            }
        }
    }

    /**
     * Find a matching redirect for the given URL
     */
    public function findMatchingRedirect(string $url): ?Redirect
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;

        // First, try exact match
        $record = RedirectRecord::find()
            ->where(['sourceUrl' => $url, 'enabled' => true])
            ->andWhere(['or', ['siteId' => null], ['siteId' => $siteId]])
            ->orderBy(['siteId' => SORT_DESC]) // Prefer site-specific redirects
            ->one();

        if ($record) {
            return $this->recordToModel($record);
        }

        // Then try regex matches
        $regexRecords = RedirectRecord::find()
            ->where(['matchType' => 'regex', 'enabled' => true])
            ->andWhere(['or', ['siteId' => null], ['siteId' => $siteId]])
            ->orderBy(['siteId' => SORT_DESC])
            ->all();

        foreach ($regexRecords as $record) {
            try {
                $pattern = '`' . $record->sourceUrl . '`';
                if (@preg_match($pattern, $url)) {
                    return $this->recordToModel($record);
                }
            } catch (\Exception $e) {
                // Log malformed regex pattern and skip
                Craft::warning(
                    "Invalid regex pattern in redirect ID {$record->id}: {$record->sourceUrl}",
                    __METHOD__
                );
                continue;
            }
        }

        return null;
    }

    /**
     * Perform the redirect
     */
    public function performRedirect(Redirect $redirect, string $originalUrl): void
    {
        $destinationUrl = $redirect->destinationUrl;

        // Handle regex replacements
        if ($redirect->matchType === 'regex') {
            $pattern = '`' . $redirect->sourceUrl . '`';
            $destinationUrl = preg_replace($pattern, $redirect->destinationUrl, $originalUrl);
        }

        // Preserve query string if setting is enabled
        $settings = Plugin::$plugin->getSettings();
        if ($settings->preserveQueryStrings) {
            $queryString = Craft::$app->getRequest()->getQueryStringWithoutPath();
            if ($queryString) {
                $separator = str_contains($destinationUrl, '?') ? '&' : '?';
                $destinationUrl .= $separator . $queryString;
            }
        }

        // Update hit count
        $this->incrementHitCount($redirect->id);

        // Perform the redirect
        Craft::$app->getResponse()->redirect($destinationUrl, $redirect->statusCode);
        Craft::$app->end();
    }

    /**
     * Increment the hit count for a redirect
     */
    public function incrementHitCount(int $redirectId): void
    {
        Craft::$app->getDb()->createCommand()
            ->update(
                '{{%redirectify_redirects}}',
                [
                    'hitCount' => new \yii\db\Expression('hitCount + 1'),
                    'lastHit' => Db::prepareDateForDb(new \DateTime()),
                ],
                ['id' => $redirectId]
            )
            ->execute();
    }

    /**
     * Log a 404 error
     */
    public function log404(string $url): void
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id;
        $referrer = Craft::$app->getRequest()->getReferrer();

        // Check if this URL is already logged
        $existing = StatsRecord::find()
            ->where(['requestedUrl' => $url, 'siteId' => $siteId])
            ->one();

        if ($existing) {
            // Update existing record
            Craft::$app->getDb()->createCommand()
                ->update(
                    '{{%redirectify_stats}}',
                    [
                        'hitCount' => new \yii\db\Expression('hitCount + 1'),
                        'lastHit' => Db::prepareDateForDb(new \DateTime()),
                        'referrer' => $referrer,
                    ],
                    ['id' => $existing->id]
                )
                ->execute();
        } else {
            // Create new record
            $record = new StatsRecord();
            $record->siteId = $siteId;
            $record->requestedUrl = $url;
            $record->hitCount = 1;
            $record->lastHit = DateTimeHelper::currentUTCDateTime();
            $record->referrer = $referrer;
            $record->handled = false;
            $record->save();

            // Trim old statistics if needed
            $this->trimStatistics();
        }
    }

    /**
     * Trim old statistics to keep the table size manageable
     */
    protected function trimStatistics(): void
    {
        $settings = Plugin::$plugin->getSettings();
        $maxStats = $settings->maxStatistics;

        $count = StatsRecord::find()->count();

        if ($count > $maxStats) {
            // Delete oldest records
            $toDelete = $count - $maxStats;
            $oldestRecords = StatsRecord::find()
                ->orderBy(['lastHit' => SORT_ASC])
                ->limit($toDelete)
                ->all();

            foreach ($oldestRecords as $record) {
                $record->delete();
            }
        }
    }

    /**
     * Get all redirects
     */
    public function getAllRedirects(?int $siteId = null): array
    {
        $query = RedirectRecord::find()->orderBy(['dateCreated' => SORT_DESC]);

        if ($siteId !== null) {
            $query->where(['or', ['siteId' => null], ['siteId' => $siteId]]);
        }

        $records = $query->all();
        return array_map(fn($record) => $this->recordToModel($record), $records);
    }

    /**
     * Get a redirect by ID
     */
    public function getRedirectById(int $id): ?Redirect
    {
        $record = RedirectRecord::findOne($id);
        return $record ? $this->recordToModel($record) : null;
    }

    /**
     * Save a redirect
     */
    public function saveRedirect(Redirect $redirect): bool
    {
        if ($redirect->id) {
            $record = RedirectRecord::findOne($redirect->id);
            if (!$record) {
                return false;
            }
        } else {
            $record = new RedirectRecord();
            // Initialize fields for new records
            $record->hitCount = 0;
            $record->lastHit = null;
        }

        $record->siteId = $redirect->siteId;
        $record->sourceUrl = $redirect->sourceUrl;
        $record->destinationUrl = $redirect->destinationUrl;
        $record->matchType = $redirect->matchType;
        $record->statusCode = $redirect->statusCode;
        $record->enabled = $redirect->enabled;

        if ($record->save()) {
            $redirect->id = $record->id;
            return true;
        }

        // Log validation errors for debugging
        if ($record->hasErrors()) {
            Craft::error('Failed to save redirect: ' . print_r($record->getErrors(), true), __METHOD__);
        }

        return false;
    }

    /**
     * Delete a redirect
     */
    public function deleteRedirect(int $id): bool
    {
        $record = RedirectRecord::findOne($id);
        if ($record) {
            return (bool) $record->delete();
        }
        return false;
    }

    /**
     * Get all 404 statistics
     */
    public function getAllStats(?int $siteId = null): array
    {
        $query = StatsRecord::find()->orderBy(['hitCount' => SORT_DESC]);

        if ($siteId !== null) {
            $query->where(['siteId' => $siteId]);
        }

        $records = $query->all();
        return array_map(fn($record) => $this->statsRecordToModel($record), $records);
    }

    /**
     * Delete a stats record
     */
    public function deleteStats(int $id): bool
    {
        $record = StatsRecord::findOne($id);
        if ($record) {
            return (bool) $record->delete();
        }
        return false;
    }

    /**
     * Create a redirect from a 404 stats entry
     */
    public function createRedirectFromStats(int $statsId, string $destinationUrl): ?Redirect
    {
        $statsRecord = StatsRecord::findOne($statsId);
        if (!$statsRecord) {
            return null;
        }

        $redirect = new Redirect();
        $redirect->siteId = $statsRecord->siteId;
        $redirect->sourceUrl = $statsRecord->requestedUrl;
        $redirect->destinationUrl = $destinationUrl;
        $redirect->matchType = 'exact';
        $redirect->statusCode = 301;
        $redirect->enabled = true;

        if ($this->saveRedirect($redirect)) {
            // Mark stats as handled
            $statsRecord->handled = true;
            $statsRecord->save();
            return $redirect;
        }

        return null;
    }

    /**
     * Convert a redirect record to a model
     */
    protected function recordToModel(RedirectRecord $record): Redirect
    {
        $redirect = new Redirect();
        $redirect->id = $record->id;
        $redirect->siteId = $record->siteId;
        $redirect->sourceUrl = $record->sourceUrl;
        $redirect->destinationUrl = $record->destinationUrl;
        $redirect->matchType = $record->matchType;
        $redirect->statusCode = $record->statusCode;
        $redirect->enabled = (bool) $record->enabled;
        $redirect->hitCount = $record->hitCount;
        $redirect->lastHit = $record->lastHit ? DateTimeHelper::toDateTime($record->lastHit) : null;
        $redirect->dateCreated = $record->dateCreated ? DateTimeHelper::toDateTime($record->dateCreated) : null;
        $redirect->dateUpdated = $record->dateUpdated ? DateTimeHelper::toDateTime($record->dateUpdated) : null;
        $redirect->uid = $record->uid;

        return $redirect;
    }

    /**
     * Convert a stats record to a model
     */
    protected function statsRecordToModel(StatsRecord $record): Stats
    {
        $stats = new Stats();
        $stats->id = $record->id;
        $stats->siteId = $record->siteId;
        $stats->requestedUrl = $record->requestedUrl;
        $stats->hitCount = $record->hitCount;
        $stats->lastHit = $record->lastHit ? DateTimeHelper::toDateTime($record->lastHit) : null;
        $stats->handled = (bool) $record->handled;
        $stats->referrer = $record->referrer;
        $stats->dateCreated = $record->dateCreated ? DateTimeHelper::toDateTime($record->dateCreated) : null;
        $stats->dateUpdated = $record->dateUpdated ? DateTimeHelper::toDateTime($record->dateUpdated) : null;
        $stats->uid = $record->uid;

        return $stats;
    }

    /**
     * Export all redirects to CSV format
     *
     * @return string CSV content
     */
    public function exportToCsv(): string
    {
        $redirects = $this->getAllRedirects();
        
        $output = fopen('php://temp', 'r+');
        
        // Write header
        fputcsv($output, [
            'Source URL',
            'Destination URL',
            'Match Type',
            'Status Code',
            'Enabled',
            'Site ID',
            'Hit Count',
            'Last Hit',
        ]);
        
        // Write data
        foreach ($redirects as $redirect) {
            fputcsv($output, [
                $redirect->sourceUrl,
                $redirect->destinationUrl,
                $redirect->matchType,
                $redirect->statusCode,
                $redirect->enabled ? '1' : '0',
                $redirect->siteId ?? '',
                $redirect->hitCount,
                $redirect->lastHit ? $redirect->lastHit->format('Y-m-d H:i:s') : '',
            ]);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Import redirects from CSV content
     *
     * @param string $csvContent
     * @return array ['success' => int, 'failed' => int, 'errors' => array]
     */
    public function importFromCsv(string $csvContent): array
    {
        $lines = str_getcsv($csvContent, "\n");
        $success = 0;
        $failed = 0;
        $errors = [];
        
        // Skip header row
        array_shift($lines);
        
        foreach ($lines as $index => $line) {
            $data = str_getcsv($line);
            
            // Skip empty lines
            if (count($data) < 4) {
                continue;
            }
            
            $redirect = new Redirect();
            $redirect->sourceUrl = $data[0] ?? '';
            $redirect->destinationUrl = $data[1] ?? '';
            $redirect->matchType = $data[2] ?? 'exact';
            $redirect->statusCode = isset($data[3]) ? (int)$data[3] : 301;
            $redirect->enabled = isset($data[4]) ? (bool)$data[4] : true;
            $redirect->siteId = isset($data[5]) && $data[5] !== '' ? (int)$data[5] : null;
            
            if ($this->saveRedirect($redirect)) {
                $success++;
            } else {
                $failed++;
                $errors[] = "Line " . ($index + 2) . ": " . implode(', ', $redirect->getErrors());
            }
        }
        
        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }
}

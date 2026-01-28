<?php

namespace rocketpark\redirectify\console\controllers;

use Craft;
use craft\console\Controller;
use craft\db\Query;
use craft\helpers\Console;
use craft\helpers\DateTimeHelper;
use rocketpark\redirectify\records\RedirectRecord;
use yii\console\ExitCode;

/**
 * Import redirects from other redirect manager plugins
 */
class ImportController extends Controller
{
    /**
     * @var bool Whether to skip duplicate redirects
     */
    public bool $skipDuplicates = true;

    /**
     * @var bool Whether to perform a dry run (don't actually import)
     */
    public bool $dryRun = false;

    /**
     * @var string|null Specific table name to import from (optional)
     */
    public ?string $table = null;

    /**
     * @inheritdoc
     */
    public function options($actionID): array
    {
        $options = parent::options($actionID);
        $options[] = 'skipDuplicates';
        $options[] = 'dryRun';
        $options[] = 'table';
        return $options;
    }

    /**
     * Import redirects from another redirect manager plugin
     *
     * @return int
     */
    public function actionIndex(): int
    {
        $this->stdout("Redirectify Import Tool\n");
        $this->stdout("======================\n\n");

        if ($this->dryRun) {
            $this->stdout("DRY RUN MODE - No changes will be made\n\n");
        }

        // Possible source tables
        $possibleTables = [
            'retour_redirects' => 'Retour',
            'dolphiq_redirects' => 'Dolphiq Redirect Manager',
            'redirect_redirects' => 'Dolphiq Redirect Manager (alt)',
            'redirectmanager_redirects' => 'Generic Redirect Manager',
        ];

        // If a specific table was provided, use only that
        if ($this->table) {
            $possibleTables = [$this->table => 'Custom Table'];
        }

        $foundTable = null;
        $pluginName = null;

        // Find which table exists
        foreach ($possibleTables as $tableName => $plugin) {
            $fullTableName = '{{%' . $tableName . '}}';
            
            if (Craft::$app->getDb()->tableExists($fullTableName)) {
                $foundTable = $tableName;
                $pluginName = $plugin;
                break;
            }
        }

        if (!$foundTable) {
            $this->stderr("No redirect manager table found.\n");
            $this->stdout("Checked for: " . implode(', ', array_keys($possibleTables)) . "\n");
            return ExitCode::OK;
        }

        $this->stdout("Found plugin: ");
        $this->stdout("{$pluginName} ({$foundTable})\n\n");

        // Count existing redirects
        $existingCount = RedirectRecord::find()->count();
        $this->stdout("Current redirects in Redirectify: {$existingCount}\n");

        // Count redirects to import
        $sourceCount = (new Query())
            ->from('{{%' . $foundTable . '}}')
            ->count();
        $this->stdout("Redirects to import: {$sourceCount}\n\n");

        if ($sourceCount == 0) {
            $this->stdout("No redirects to import.\n");
            return ExitCode::OK;
        }

        // Confirm before proceeding
        if (!$this->dryRun && !$this->confirm('Do you want to proceed with the import?')) {
            $this->stdout("Import cancelled.\n");
            return ExitCode::OK;
        }

        // Perform the import
        $result = $this->importFromTable($foundTable);

        $this->stdout("\n");
        $this->stdout("Import Summary\n");
        $this->stdout("==============\n");
        $this->stdout("Successfully imported: {$result['imported']}\n");
        
        if ($result['skipped'] > 0) {
            $this->stdout("Skipped (duplicates): {$result['skipped']}\n");
        }
        
        if ($result['failed'] > 0) {
            $this->stdout("Failed: {$result['failed']}\n");
        }

        return ExitCode::OK;
    }

    /**
     * Show all database tables with 'redirect' in the name
     *
     * @return int
     */
    public function actionTables(): int
    {
        $this->stdout("Database Tables\n");
        $this->stdout("===============\n\n");

        $allTables = Craft::$app->db->schema->tableNames;
        $redirectTables = array_filter($allTables, fn($t) => stripos($t, 'redirect') !== false);

        if (empty($redirectTables)) {
            $this->stdout("No tables found with 'redirect' in the name.\n");
        } else {
            $this->stdout("Tables containing 'redirect':\n");
            foreach ($redirectTables as $table) {
                $this->stdout("  - {$table}\n");
            }
        }

        return ExitCode::OK;
    }

    /**
     * List all redirects from the source plugin
     *
     * @return int
     */
    public function actionList(): int
    {
        $possibleTables = [
            'retour_redirects',
            'dolphiq_redirects',
            'redirect_redirects',
            'redirectmanager_redirects',
        ];

        if ($this->table) {
            $possibleTables = [$this->table];
        }

        $foundTable = null;

        foreach ($possibleTables as $tableName) {
            $fullTableName = '{{%' . $tableName . '}}';
            
            if (Craft::$app->getDb()->tableExists($fullTableName)) {
                $foundTable = $tableName;
                break;
            }
        }

        if (!$foundTable) {
            $this->stderr("No redirect manager table found.\n");
            return ExitCode::OK;
        }

        $this->stdout("Redirects in {$foundTable}:\n\n");

        // Get columns from the table
        $schema = Craft::$app->getDb()->getTableSchema('{{%' . $foundTable . '}}');
        $columns = $schema->columnNames;

        // Find source and destination column names
        $sourceCol = $this->findColumn($columns, ['redirectSrcUrl', 'sourceUrl', 'source_url', 'oldUrl', 'from']);
        $destCol = $this->findColumn($columns, ['redirectDestUrl', 'destinationUrl', 'destination_url', 'newUrl', 'to']);

        if (!$sourceCol || !$destCol) {
            $this->stderr("Could not identify source/destination columns.\n");
            $this->stdout("Available columns: " . implode(', ', $columns) . "\n");
            return ExitCode::OK;
        }

        $redirects = (new Query())
            ->select([$sourceCol, $destCol])
            ->from('{{%' . $foundTable . '}}')
            ->limit(20)
            ->all();

        foreach ($redirects as $redirect) {
            $this->stdout("{$redirect[$sourceCol]} → {$redirect[$destCol]}\n");
        }

        $total = (new Query())->from('{{%' . $foundTable . '}}')->count();
        if ($total > 20) {
            $this->stdout("\n... and " . ($total - 20) . " more\n");
        }

        return ExitCode::OK;
    }

    /**
     * Import redirects from a table
     */
    private function importFromTable(string $tableName): array
    {
        $imported = 0;
        $skipped = 0;
        $failed = 0;

        // Get table schema
        $schema = Craft::$app->getDb()->getTableSchema('{{%' . $tableName . '}}');
        $columns = $schema->columnNames;

        // Find column mappings
        $sourceCol = $this->findColumn($columns, ['redirectSrcUrl', 'sourceUrl', 'source_url', 'oldUrl', 'from', 'src']);
        $destCol = $this->findColumn($columns, ['redirectDestUrl', 'destinationUrl', 'destination_url', 'newUrl', 'to', 'dest']);
        $statusCol = $this->findColumn($columns, ['redirectHttpCode', 'statusCode', 'status_code', 'httpCode', 'code']);
        $matchTypeCol = $this->findColumn($columns, ['redirectMatchType', 'matchType', 'match_type', 'type']);
        $hitCountCol = $this->findColumn($columns, ['hitCount', 'hit_count', 'counter', 'hits']);
        $enabledCol = $this->findColumn($columns, ['enabled', 'active']);
        $siteIdCol = $this->findColumn($columns, ['siteId', 'site_id']);

        $select = array_filter([
            $sourceCol,
            $destCol,
            $statusCol,
            $matchTypeCol,
            $hitCountCol,
            $enabledCol,
            $siteIdCol,
        ]);

        $sourceRedirects = (new Query())
            ->select($select)
            ->from('{{%' . $tableName . '}}')
            ->all();

        foreach ($sourceRedirects as $source) {
            $sourceUrl = $source[$sourceCol] ?? '';
            $destinationUrl = $source[$destCol] ?? '';

            if (!$sourceUrl || !$destinationUrl) {
                $failed++;
                continue;
            }

            // Check for duplicates
            if ($this->skipDuplicates) {
                $exists = RedirectRecord::find()
                    ->where(['sourceUrl' => $sourceUrl])
                    ->exists();

                if ($exists) {
                    $this->stdout(".");
                    $skipped++;
                    continue;
                }
            }

            if ($this->dryRun) {
                $this->stdout("Would import: {$sourceUrl} → {$destinationUrl}\n");
                $imported++;
                continue;
            }

            // Detect match type
            $matchType = 'exact';
            if ($matchTypeCol && !empty($source[$matchTypeCol])) {
                $matchType = in_array(strtolower($source[$matchTypeCol]), ['regex', 'regexmatch']) 
                    ? 'regex' 
                    : 'exact';
            } else {
                // Auto-detect regex patterns
                if ($this->isRegexPattern($sourceUrl)) {
                    $matchType = 'regex';
                }
            }

            // Create record
            $record = new RedirectRecord();
            $record->siteId = $siteIdCol ? ($source[$siteIdCol] ?? null) : null;
            $record->sourceUrl = $this->normalizeUrl($sourceUrl);
            $record->destinationUrl = $destinationUrl;
            $record->matchType = $matchType;
            $record->statusCode = $statusCol ? (int)($source[$statusCol] ?? 301) : 301;
            $record->enabled = $enabledCol ? (bool)($source[$enabledCol] ?? true) : true;
            $record->hitCount = $hitCountCol ? (int)($source[$hitCountCol] ?? 0) : 0;
            $record->lastHit = null;
            $record->dateCreated = DateTimeHelper::currentUTCDateTime();
            $record->dateUpdated = DateTimeHelper::currentUTCDateTime();

            if ($record->save()) {
                $this->stdout(".");
                $imported++;
            } else {
                $this->stdout("x");
                $failed++;
            }
        }

        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'failed' => $failed,
        ];
    }

    /**
     * Find a column name from a list of possibilities
     */
    private function findColumn(array $columns, array $possibilities): ?string
    {
        foreach ($possibilities as $name) {
            if (in_array($name, $columns)) {
                return $name;
            }
        }
        return null;
    }

    /**
     * Normalize URL
     */
    private function normalizeUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            $parsed = parse_url($url);
            return $parsed['path'] ?? '/';
        }
        
        if (!str_starts_with($url, '/') && !str_starts_with($url, '^')) {
            $url = '/' . $url;
        }
        
        return $url;
    }

    /**
     * Check if a URL is a regex pattern
     */
    private function isRegexPattern(string $url): bool
    {
        $regexIndicators = ['^', '$', '(.*)', '(.+)', '[', '|', '\\d', '\\w'];
        
        foreach ($regexIndicators as $indicator) {
            if (str_contains($url, $indicator)) {
                return true;
            }
        }
        
        return false;
    }
}

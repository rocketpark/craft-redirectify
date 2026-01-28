<?php

namespace rocketpark\redirectify\models;

use craft\base\Model;

/**
 * Redirect Model
 */
class Redirect extends Model
{
    /**
     * @var int|null
     */
    public ?int $id = null;

    /**
     * @var int|null
     */
    public ?int $siteId = null;

    /**
     * Source URL pattern
     * @var string
     */
    public string $sourceUrl = '';

    /**
     * Destination URL
     * @var string
     */
    public string $destinationUrl = '';

    /**
     * Match type: 'exact' or 'regex'
     * @var string
     */
    public string $matchType = 'exact';

    /**
     * HTTP status code: 301 or 302
     * @var int
     */
    public int $statusCode = 301;

    /**
     * Whether the redirect is enabled
     * @var bool
     */
    public bool $enabled = true;

    /**
     * Number of times this redirect has been hit
     * @var int
     */
    public int $hitCount = 0;

    /**
     * Last time this redirect was hit
     * @var \DateTime|null
     */
    public ?\DateTime $lastHit = null;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateCreated = null;

    /**
     * @var \DateTime|null
     */
    public ?\DateTime $dateUpdated = null;

    /**
     * @var string|null
     */
    public ?string $uid = null;

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        return [
            [['sourceUrl', 'destinationUrl'], 'required'],
            [['sourceUrl', 'destinationUrl'], 'string', 'max' => 500],
            [['matchType'], 'in', 'range' => ['exact', 'regex']],
            [['statusCode'], 'in', 'range' => [301, 302]],
            [['enabled'], 'boolean'],
            [['hitCount'], 'integer', 'min' => 0],
            [['siteId', 'id'], 'integer'],
            [['sourceUrl'], 'validateRegexPattern'],
            [['sourceUrl'], 'normalizeSourceUrl'],
        ];
    }

    /**
     * Normalize source URL to strip protocol and domain
     */
    public function normalizeSourceUrl($attribute, $params): void
    {
        if (empty($this->$attribute)) {
            return;
        }

        $url = $this->$attribute;

        // Check if it looks like a full URL with protocol
        if (preg_match('#^https?://#i', $url)) {
            // Parse the URL and extract just the path and query
            $parsed = parse_url($url);
            
            $normalized = '';
            
            // Add path
            if (!empty($parsed['path'])) {
                $normalized .= $parsed['path'];
            } else {
                $normalized = '/';
            }
            
            // Add query string if present
            if (!empty($parsed['query'])) {
                $normalized .= '?' . $parsed['query'];
            }
            
            // Add fragment if present
            if (!empty($parsed['fragment'])) {
                $normalized .= '#' . $parsed['fragment'];
            }
            
            $this->$attribute = $normalized;
        }
    }

    /**
     * Validate regex pattern if matchType is regex
     */
    public function validateRegexPattern($attribute, $params): void
    {
        if ($this->matchType === 'regex') {
            $pattern = $this->$attribute;
            
            // Test if regex pattern is valid
            if (@preg_match('`' . $pattern . '`', '') === false) {
                $this->addError($attribute, 'Invalid regular expression pattern.');
            }
        }
    }

    /**
     * Get match type options for dropdown
     */
    public static function getMatchTypeOptions(): array
    {
        return [
            'exact' => 'Exact Match',
            'regex' => 'Regular Expression',
        ];
    }

    /**
     * Get status code options for dropdown
     */
    public static function getStatusCodeOptions(): array
    {
        return [
            301 => '301 - Permanent Redirect',
            302 => '302 - Temporary Redirect',
        ];
    }
}

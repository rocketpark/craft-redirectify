<?php

namespace rocketpark\redirectify\models;

use craft\base\Model;

/**
 * Redirectify Settings Model
 */
class Settings extends Model
{
    /**
     * Whether to track 404 statistics
     * @var bool
     */
    public bool $trackStatistics = true;

    /**
     * Whether to strip query strings from 404 URLs
     * @var bool
     */
    public bool $stripQueryStrings = false;

    /**
     * Whether to preserve query strings when redirecting
     * @var bool
     */
    public bool $preserveQueryStrings = true;

    /**
     * Maximum number of 404 statistics to store
     * @var int
     */
    public int $maxStatistics = 1000;

    /**
     * Whether to automatically create redirects when entry slugs change
     * @var bool
     */
    public bool $createEntryRedirects = true;

    /**
     * @inheritdoc
     */
    public function defineRules(): array
    {
        return [
            [['trackStatistics', 'stripQueryStrings', 'preserveQueryStrings', 'createEntryRedirects'], 'boolean'],
            [['maxStatistics'], 'integer', 'min' => 100, 'max' => 10000],
        ];
    }
}

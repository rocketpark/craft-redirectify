<?php

namespace rocketpark\redirectify\models;

use craft\base\Model;

/**
 * Stats Model for 404 tracking
 */
class Stats extends Model
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
     * The requested URL that resulted in 404
     * @var string
     */
    public string $requestedUrl = '';

    /**
     * Number of times this 404 has occurred
     * @var int
     */
    public int $hitCount = 0;

    /**
     * Last time this 404 occurred
     * @var \DateTime|null
     */
    public ?\DateTime $lastHit = null;

    /**
     * Whether this 404 was eventually handled (redirect created)
     * @var bool
     */
    public bool $handled = false;

    /**
     * The referrer URL (where the visitor came from)
     * @var string|null
     */
    public ?string $referrer = null;

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
            [['requestedUrl'], 'required'],
            [['requestedUrl'], 'string', 'max' => 500],
            [['referrer'], 'string', 'max' => 500],
            [['handled'], 'boolean'],
            [['hitCount'], 'integer', 'min' => 0],
            [['siteId', 'id'], 'integer'],
        ];
    }
}

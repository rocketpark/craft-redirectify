<?php

namespace rocketpark\redirectify\records;

use craft\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * Stats Record for 404 tracking
 *
 * @property int $id
 * @property int $siteId
 * @property string $requestedUrl
 * @property int $hitCount
 * @property \DateTime $lastHit
 * @property bool $handled
 * @property string $referrer
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class StatsRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%redirectify_stats}}';
    }

    /**
     * @inheritdoc
     */
    public function behaviors(): array
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'dateCreated',
                'updatedAtAttribute' => 'dateUpdated',
                'value' => new Expression('NOW()'),
            ],
        ];
    }
}

<?php

namespace rocketpark\redirectify\records;

use craft\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * Redirect Record
 *
 * @property int $id
 * @property int $siteId
 * @property string $sourceUrl
 * @property string $destinationUrl
 * @property string $matchType
 * @property int $statusCode
 * @property bool $enabled
 * @property int $hitCount
 * @property \DateTime $lastHit
 * @property \DateTime $dateCreated
 * @property \DateTime $dateUpdated
 * @property string $uid
 */
class RedirectRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%redirectify_redirects}}';
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

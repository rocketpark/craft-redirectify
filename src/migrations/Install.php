<?php

namespace rocketpark\redirectify\migrations;

use Craft;
use craft\db\Migration;

/**
 * Install migration for Redirectify plugin
 */
class Install extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%redirectify_stats}}');
        $this->dropTableIfExists('{{%redirectify_redirects}}');

        return true;
    }

    /**
     * Create the database tables
     */
    protected function createTables(): void
    {
        // Redirects table
        $this->createTable('{{%redirectify_redirects}}', [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer(),
            'sourceUrl' => $this->string(500)->notNull(),
            'destinationUrl' => $this->string(500)->notNull(),
            'matchType' => $this->string(32)->notNull()->defaultValue('exact'),
            'statusCode' => $this->integer()->notNull()->defaultValue(301),
            'enabled' => $this->boolean()->notNull()->defaultValue(true),
            'hitCount' => $this->integer()->notNull()->defaultValue(0),
            'lastHit' => $this->dateTime(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        // 404 Statistics table
        $this->createTable('{{%redirectify_stats}}', [
            'id' => $this->primaryKey(),
            'siteId' => $this->integer(),
            'requestedUrl' => $this->string(500)->notNull(),
            'hitCount' => $this->integer()->notNull()->defaultValue(1),
            'lastHit' => $this->dateTime(),
            'handled' => $this->boolean()->notNull()->defaultValue(false),
            'referrer' => $this->string(500),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);
    }

    /**
     * Create indexes for better query performance
     */
    protected function createIndexes(): void
    {
        // Redirects indexes
        $this->createIndex(
            null,
            '{{%redirectify_redirects}}',
            ['sourceUrl'],
            false
        );
        $this->createIndex(
            null,
            '{{%redirectify_redirects}}',
            ['siteId'],
            false
        );
        $this->createIndex(
            null,
            '{{%redirectify_redirects}}',
            ['enabled'],
            false
        );

        // Stats indexes
        $this->createIndex(
            null,
            '{{%redirectify_stats}}',
            ['requestedUrl'],
            false
        );
        $this->createIndex(
            null,
            '{{%redirectify_stats}}',
            ['siteId'],
            false
        );
        $this->createIndex(
            null,
            '{{%redirectify_stats}}',
            ['handled'],
            false
        );
    }

    /**
     * Add foreign keys
     */
    protected function addForeignKeys(): void
    {
        // Link redirects to sites
        $this->addForeignKey(
            null,
            '{{%redirectify_redirects}}',
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE',
            'CASCADE'
        );

        // Link stats to sites
        $this->addForeignKey(
            null,
            '{{%redirectify_stats}}',
            ['siteId'],
            '{{%sites}}',
            ['id'],
            'CASCADE',
            'CASCADE'
        );
    }
}

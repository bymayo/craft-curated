<?php

namespace bymayo\curate\migrations;

use craft\db\Migration;

/**
 * Install migration
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%curate_relations}}', [
            'id' => $this->primaryKey(),
            'fieldId' => $this->integer()->notNull(),
            'sourceId' => $this->integer()->notNull(),
            'sourceSiteId' => $this->integer()->null(),
            'targetId' => $this->integer()->notNull(),
            'sortOrder' => $this->integer()->notNull(),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(
            null,
            '{{%curate_relations}}',
            ['fieldId', 'sourceId', 'sourceSiteId', 'targetId'],
            true
        );
        $this->createIndex(null, '{{%curate_relations}}', ['sourceId', 'sortOrder']);
        $this->createIndex(null, '{{%curate_relations}}', ['targetId']);

        $this->addForeignKey(null, '{{%curate_relations}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%curate_relations}}', ['sourceId'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%curate_relations}}', ['targetId'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%curate_relations}}', ['sourceSiteId'], '{{%sites}}', ['id'], 'CASCADE');

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%curate_relations}}');
        return true;
    }
}

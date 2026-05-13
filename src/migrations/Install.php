<?php

namespace bymayo\curated\migrations;

use craft\db\Migration;

/**
 * Install migration
 */
class Install extends Migration
{
    public function safeUp(): bool
    {
        $this->createTable('{{%curated_relations}}', [
            'id' => $this->primaryKey(),
            'fieldId' => $this->integer()->notNull(),
            'sourceId' => $this->integer()->notNull(),
            'sourceSiteId' => $this->integer()->null(),
            'targetId' => $this->integer()->notNull(),
            'sortOrder' => $this->integer()->notNull(),
            'pinned' => $this->boolean()->notNull()->defaultValue(false),
            'dateCreated' => $this->dateTime()->notNull(),
            'dateUpdated' => $this->dateTime()->notNull(),
            'uid' => $this->uid(),
        ]);

        $this->createIndex(
            null,
            '{{%curated_relations}}',
            ['fieldId', 'sourceId', 'sourceSiteId', 'targetId'],
            true
        );
        $this->createIndex(null, '{{%curated_relations}}', ['sourceId', 'sortOrder']);
        $this->createIndex(null, '{{%curated_relations}}', ['targetId']);

        $this->addForeignKey(null, '{{%curated_relations}}', ['fieldId'], '{{%fields}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%curated_relations}}', ['sourceId'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%curated_relations}}', ['targetId'], '{{%elements}}', ['id'], 'CASCADE');
        $this->addForeignKey(null, '{{%curated_relations}}', ['sourceSiteId'], '{{%sites}}', ['id'], 'CASCADE');

        return true;
    }

    public function safeDown(): bool
    {
        $this->dropTableIfExists('{{%curated_relations}}');
        return true;
    }
}

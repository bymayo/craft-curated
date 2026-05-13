<?php

namespace bymayo\curated\migrations;

use craft\db\Migration;

/**
 * Adds the `pinned` column to {{%curated_relations}} for installs from
 * before pinning landed. Idempotent: skips if the column already exists.
 */
class m260512_170000_AddPinnedColumn extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%curated_relations}}';
        $rawTable = $this->db->getSchema()->getRawTableName($table);
        $schema = $this->db->getSchema()->getTableSchema($rawTable);

        if ($schema && !isset($schema->columns['pinned'])) {
            $this->addColumn(
                $table,
                'pinned',
                $this->boolean()->notNull()->defaultValue(false)->after('sortOrder')
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}

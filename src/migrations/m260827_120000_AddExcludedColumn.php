<?php

namespace bymayo\curated\migrations;

use craft\db\Migration;

/**
 * Adds the `excluded` column to {{%curated_relations}}.
 *
 * Excluded rows are tombstones: a target the editor removed from a field
 * that populates from all elements. Without them, auto-discovery would pull
 * the element straight back in on the next load, since there's no native
 * relation to delete in that mode.
 *
 * Idempotent: skips if the column already exists.
 */
class m260827_120000_AddExcludedColumn extends Migration
{
    public function safeUp(): bool
    {
        $table = '{{%curated_relations}}';
        $rawTable = $this->db->getSchema()->getRawTableName($table);
        $schema = $this->db->getSchema()->getTableSchema($rawTable);

        if ($schema && !isset($schema->columns['excluded'])) {
            $this->addColumn(
                $table,
                'excluded',
                $this->boolean()->notNull()->defaultValue(false)->after('pinned')
            );
        }

        return true;
    }

    public function safeDown(): bool
    {
        return true;
    }
}

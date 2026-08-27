<?php

namespace bymayo\curated\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $fieldId
 * @property int $sourceId
 * @property int|null $sourceSiteId
 * @property int $targetId
 * @property int $sortOrder
 * @property bool $pinned
 * @property bool $excluded
 */
class CuratedRelation extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%curated_relations}}';
    }
}

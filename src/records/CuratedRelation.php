<?php

namespace bymayo\curate\records;

use craft\db\ActiveRecord;

/**
 * @property int $id
 * @property int $fieldId
 * @property int $sourceId
 * @property int|null $sourceSiteId
 * @property int $targetId
 * @property int $sortOrder
 */
class CuratedRelation extends ActiveRecord
{
    public static function tableName(): string
    {
        return '{{%curate_relations}}';
    }
}

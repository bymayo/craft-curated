<?php

namespace bymayo\curated\models;

use craft\base\Model;

/**
 * Curated settings
 */
class Settings extends Model
{
    /**
     * Append newly related elements to the end of the curated order
     * automatically (e.g. when a Product is saved with a new Category,
     * add it to the end of that Category's curated list).
     */
    public bool $autoAppendNewItems = true;

    /**
     * Remove curated entries when the underlying native relation is
     * removed (e.g. Product un-categorised → drop from curated list).
     */
    public bool $pruneOnRelationRemoved = true;

    public function defineRules(): array
    {
        return [
            [['autoAppendNewItems', 'pruneOnRelationRemoved'], 'boolean'],
        ];
    }
}

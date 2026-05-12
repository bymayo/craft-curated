<?php

namespace bymayo\curated\models;

use Craft;
use craft\base\Model;

/**
 * Curated settings
 */
class Settings extends Model
{
    /**
     * Subtle notice rendered below every Curated field input, telling
     * editors how the field behaves. Set to an empty string to hide it.
     */
    public string $editorNotice = '';

    /**
     * When true, removing a chip from a Curated field also deletes the
     * underlying native relation rows between parent and target on save.
     * Destructive: edits the other side of the relation, any direction,
     * any relation field. Off by default.
     */
    public bool $removeNativeRelations = false;

    public function init(): void
    {
        parent::init();
        if ($this->editorNotice === '') {
            $this->editorNotice = Craft::t('curated', 'Auto-populated from related items. Drag to reorder, or use the menu on each item for quick moves.');
        }
    }

    public function defineRules(): array
    {
        return [
            [['editorNotice'], 'string'],
            [['removeNativeRelations'], 'boolean'],
        ];
    }
}

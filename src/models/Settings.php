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
        ];
    }
}

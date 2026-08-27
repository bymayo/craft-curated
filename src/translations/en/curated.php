<?php

/**
 * Curated translations.
 *
 * Returns an array of string translations for the `curated` category.
 * Keys are the source English strings exactly as passed to `Craft::t('curated', ...)`
 * or the `|t('curated')` filter. Values are the localized version.
 *
 * Copy this file to `src/translations/<locale>/curated.php` and translate
 * the values to provide a new language.
 */

return [
    // Field settings
    'Element type' => 'Element type',
    'Which element type can be curated in this field.' => 'Which element type can be curated in this field.',
    'Sources' => 'Sources',
    'Which sources do you want to select elements from?' => 'Which sources do you want to select elements from?',
    'No sources defined for this element type yet.' => 'No sources defined for this element type yet.',
    'Populate from all elements' => 'Populate from all elements',
    'Fill this field with every element from the sources above, ignoring relations. Use it for a page nothing relates to, e.g. a blog listing page. Sources can\'t be set to All. Every element loads into the field, so large sections will be slow to edit.' => 'Fill this field with every element from the sources above, ignoring relations. Use it for a page nothing relates to, e.g. a blog listing page. Sources can\'t be set to All. Every element loads into the field, so large sections will be slow to edit.',
    'Choose specific sources above. This setting can\'t be used with "All".' => 'Choose specific sources above. This setting can\'t be used with "All".',
    'Default Placement' => 'Default Placement',
    'Where new elements should be placed by default in the field.' => 'Where new elements should be placed by default in the field.',
    'View Mode' => 'View Mode',
    'Choose how the field should look for authors.' => 'Choose how the field should look for authors.',
    'Allow adding elements' => 'Allow adding elements',
    'Show add button. Items added through it appear only in this field, not elsewhere as related items — and with "Populate from all elements" on, it\'s how an editor puts back an element they removed.' => 'Show add button. Items added through it appear only in this field, not elsewhere as related items — and with "Populate from all elements" on, it\'s how an editor puts back an element they removed.',
    '"Add" Button Label' => '"Add" Button Label',
    'The text label for element selection buttons.' => 'The text label for element selection buttons.',

    // View modes
    'List' => 'List',
    'Inline list' => 'Inline list',
    'Cards' => 'Cards',
    'Card grid' => 'Card grid',

    // Default Placement / Sort options
    'After other elements' => 'After other elements',
    'Before other elements' => 'Before other elements',
    'Title (A–Z)' => 'Title (A–Z)',
    'Title (Z–A)' => 'Title (Z–A)',
    'Date created (newest first)' => 'Date created (newest first)',
    'Date created (oldest first)' => 'Date created (oldest first)',
    'Date updated (newest first)' => 'Date updated (newest first)',
    'Random' => 'Random',
    'Price (low to high)' => 'Price (low to high)',
    'Price (high to low)' => 'Price (high to low)',

    // Inline sort dropdown + search
    'Sort by' => 'Sort by',
    'Search' => 'Search',
    'Overwrite the current order?' => 'Overwrite the current order?',

    // Chip-menu quick reorder
    'Move to top' => 'Move to top',
    'Move to bottom' => 'Move to bottom',
    'Move to position…' => 'Move to position…',
    'Move to position (1 to {total}):' => 'Move to position (1 to {total}):',
    'Pin' => 'Pin',
    'Unpin' => 'Unpin',

    // Plugin settings
    'Notice' => 'Notice',
    'Subtle help text rendered below every Curated field input. Leave blank to hide.' => 'Subtle help text rendered below every Curated field input. Leave blank to hide.',
    'Auto-populated from related items. Drag to reorder, or use the menu on each item for quick moves.' => 'Auto-populated from related items. Drag to reorder, or use the menu on each item for quick moves.',
    'Fully remove on delete' => 'Fully remove on delete',
    'Also deletes the underlying relation. No undo.' => 'Also deletes the underlying relation. No undo.',
    'Removing an item edits the canonical relation, not just this field.' => 'Removing an item edits the canonical relation, not just this field.',

    // Element index column placeholder
    'Curated {type}' => 'Curated {type}',

    // Curated Sync utility
    'Curated Sync' => 'Curated Sync',
    'Syncs and adds any missing related elements to each Curated field. Safe to re-run, already-curated items stay in place.' => 'Syncs and adds any missing related elements to each Curated field. Safe to re-run, already-curated items stay in place.',
    'Sync Curated Fields' => 'Sync Curated Fields',
];

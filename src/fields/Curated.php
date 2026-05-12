<?php

namespace bymayo\curated\fields;

use bymayo\curated\Plugin;
use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\db\ElementQuery;
use craft\helpers\Cp;

/**
 * Curated field
 *
 * Placed on the PARENT element (e.g. a Category). Holds a manually
 * ordered list of child elements (e.g. Products) scoped to THIS parent
 * only, so the same target can sit in different positions across
 * different parents — the thing the native relations table can't do.
 *
 * The field is polymorphic: pick any element type at field config time
 * (Entry, Category, Asset, User, Tag, Commerce Product, …), then
 * narrow by source (Section, Category Group, Volume, User Group, …).
 *
 *   {% set products = category.curatedProducts.all() %}
 *   {% set top3    = category.curatedProducts.limit(3).all() %}
 */
class Curated extends Field
{
    /** Element types offered in the field config picker. Commerce types are optional. */
    private const SUPPORTED_TYPES = [
        \craft\elements\Asset::class,
        \craft\elements\Category::class,
        \craft\elements\Entry::class,
        \craft\elements\User::class,
        'craft\\commerce\\elements\\Product',
        'craft\\commerce\\elements\\Variant',
    ];

    /** Fully-qualified element class — e.g. craft\elements\Entry::class */
    public string $targetElementType = '';

    /**
     * Source keys to restrict the picker to, or '*' for all sources.
     * Matches the convention used by Craft's native relation fields,
     * e.g. ['section:abc-uid', 'section:def-uid'] for entries.
     *
     * @var string|string[]
     */
    public string|array $sources = '*';

    /** 'list' or 'large' — matches native Entries / Assets fields */
    public string $viewMode = 'list';

    /**
     * Initial ordering applied to auto-discovered native relations that
     * aren't yet in the curated order. Once an editor drags, that order
     * is persisted and this setting no longer applies to those items.
     */
    public string $initialSort = self::SORT_NONE;

    public const SORT_NONE = 'none';
    public const SORT_PLACE_AT_TOP = 'placeAtTop';
    public const SORT_TITLE_ASC = 'titleAsc';
    public const SORT_TITLE_DESC = 'titleDesc';
    public const SORT_DATE_CREATED_DESC = 'dateCreatedDesc';
    public const SORT_DATE_CREATED_ASC = 'dateCreatedAsc';
    public const SORT_DATE_UPDATED_DESC = 'dateUpdatedDesc';
    public const SORT_RANDOM = 'random';

    public const SORT_OPTIONS = [
        self::SORT_NONE,
        self::SORT_PLACE_AT_TOP,
        self::SORT_TITLE_ASC,
        self::SORT_TITLE_DESC,
        self::SORT_DATE_CREATED_DESC,
        self::SORT_DATE_CREATED_ASC,
        self::SORT_DATE_UPDATED_DESC,
        self::SORT_RANDOM,
    ];

    public static function displayName(): string
    {
        return 'Curated';
    }

    public static function phpType(): string
    {
        return ElementQuery::class;
    }

    public static function dbType(): array|string|null
    {
        return null;
    }

    public function defineRules(): array
    {
        return array_merge(parent::defineRules(), [
            [['targetElementType'], 'required'],
            [['targetElementType'], 'string'],
            [['viewMode'], 'in', 'range' => ['list', 'large']],
            [['initialSort'], 'in', 'range' => self::SORT_OPTIONS],
            [['sources'], 'safe'],
        ]);
    }

    /**
     * Legacy shim — earlier builds had a `trackRelationFieldHandle` setting
     * persisted in field settings JSON. Swallow it on load so Yii doesn't
     * throw on existing rows. Remove once all sites have re-saved their
     * Curated fields.
     */
    public function setTrackRelationFieldHandle(mixed $value): void
    {
    }

    public function beforeValidate(): bool
    {
        if (is_array($this->sources)) {
            $filtered = array_values(array_filter(
                $this->sources,
                fn($v) => $v !== '' && $v !== null
            ));
            // "All" picked, or nothing picked → collapse to '*'
            if (!$filtered || in_array('*', $filtered, true)) {
                $this->sources = '*';
            } else {
                $this->sources = $filtered;
            }
        }
        return parent::beforeValidate();
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('curated/_field/settings', [
            'field' => $this,
            'elementTypes' => $this->elementTypeOptions(),
            'sourcesByType' => $this->sourcesByType(),
        ]);
    }

    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        $elements = $value instanceof ElementQuery
            ? $value->status(null)->all()
            : [];

        $this->registerFieldJs();

        $pickerHtml = Cp::elementSelectHtml([
            'name' => $this->handle,
            'elementType' => $this->targetElementType,
            'sources' => $this->sources === '*' ? null : $this->sources,
            'elements' => $elements,
            'sortable' => true,
            'viewMode' => $this->viewMode,
            'showSiteMenu' => true,
            'fieldId' => $this->id,
            // Marker on the picker's container so the chip-menu JS patch can
            // detect this is a Curated field and only add its extra items
            // here, not in other element pickers across the CP.
            'containerAttributes' => [
                'data' => ['curated' => '1'],
            ],
        ]);

        return $this->renderSortToolbar($element) . $pickerHtml . $this->renderEditorNotice();
    }

    private function renderEditorNotice(): string
    {
        $text = trim((string)Plugin::getInstance()->getSettings()->editorNotice);
        if ($text === '') {
            return '';
        }
        return sprintf(
            '<p class="curated-editor-notice light"><span data-icon="info" class="curated-editor-notice-icon" aria-hidden="true"></span>%s</p>',
            htmlspecialchars($text)
        );
    }

    private function renderSortToolbar(?ElementInterface $element): string
    {
        $fieldId = (int)($this->id ?? 0);
        $sourceId = (int)($element?->id ?? 0);
        $siteId = (int)($element?->siteId ?? 0);

        $selectHtml = Cp::selectHtml([
            'inputAttributes' => [
                'class' => 'curated-sort-select',
            ],
            'options' => [
                ['value' => '', 'label' => Craft::t('curated', 'Sort by…')],
                ['value' => self::SORT_TITLE_ASC, 'label' => Craft::t('curated', 'Title (A–Z)')],
                ['value' => self::SORT_TITLE_DESC, 'label' => Craft::t('curated', 'Title (Z–A)')],
                ['value' => self::SORT_DATE_CREATED_DESC, 'label' => Craft::t('curated', 'Date created (newest first)')],
                ['value' => self::SORT_DATE_CREATED_ASC, 'label' => Craft::t('curated', 'Date created (oldest first)')],
                ['value' => self::SORT_DATE_UPDATED_DESC, 'label' => Craft::t('curated', 'Date updated (newest first)')],
                ['value' => self::SORT_RANDOM, 'label' => Craft::t('curated', 'Random')],
            ],
            'value' => '',
        ]);

        return sprintf(
            '<div class="curated-sort-toolbar" data-field-id="%d" data-source-id="%d" data-site-id="%d">%s</div>',
            $fieldId,
            $sourceId,
            $siteId,
            $selectHtml
        );
    }

    /**
     * Adds Move to top / bottom / position N to each chip's action menu in
     * a Curated picker, alongside Craft's native Move up / Move down.
     */
    private function registerFieldJs(): void
    {
        $labelTop = json_encode(Craft::t('curated', 'Move to top'));
        $labelBottom = json_encode(Craft::t('curated', 'Move to bottom'));
        $labelPosition = json_encode(Craft::t('curated', 'Move to position…'));
        $labelPrompt = json_encode(Craft::t('curated', 'Move to position (1 to {total}):'));
        $labelSortConfirm = json_encode(Craft::t('curated', 'Overwrite the current order?'));
        $sortActionUrl = json_encode(\craft\helpers\UrlHelper::actionUrl('curated/sort/run'));

        $js = <<<JS
(function() {
    function makeExtraActions(\$element, picker) {
        var container = picker.\$elementsContainer;
        function \$li() { return \$element.closest('li'); }
        return [{
            icon: 'arrow-up-to-line',
            label: {$labelTop},
            callback: function() {
                var \$row = \$li();
                if (!\$row.length) return;
                container.prepend(\$row);
                picker.onSortChange();
            },
        }, {
            icon: 'arrow-down-to-line',
            label: {$labelBottom},
            callback: function() {
                var \$row = \$li();
                if (!\$row.length) return;
                container.append(\$row);
                picker.onSortChange();
            },
        }, {
            icon: 'list-ol',
            label: {$labelPosition},
            callback: function() {
                var \$row = \$li();
                if (!\$row.length) return;
                var total = container.children('li').length;
                var promptText = {$labelPrompt}.replace('{total}', total);
                var input = window.prompt(promptText, '1');
                if (input === null) return;
                var pos = parseInt(input, 10);
                if (isNaN(pos)) return;
                pos = Math.max(1, Math.min(pos, total));
                var others = container.children('li').not(\$row);
                var idx = pos - 1;
                if (idx >= others.length) {
                    container.append(\$row);
                } else {
                    others.eq(idx).before(\$row);
                }
                picker.onSortChange();
            },
        }];
    }

    // Patch defineElementActions so any chips added later (via "Add an
    // element") get our extra items when their menu is built.
    if (typeof Craft !== 'undefined' && Craft.BaseElementSelectInput && !Craft.BaseElementSelectInput.prototype._curatedPatched) {
        Craft.BaseElementSelectInput.prototype._curatedPatched = true;
        var orig = Craft.BaseElementSelectInput.prototype.defineElementActions;
        Craft.BaseElementSelectInput.prototype.defineElementActions = function(\$element) {
            var actions = orig ? orig.call(this, \$element) : [];
            if (!this.settings || !this.settings.sortable) return actions;
            if (!this.\$container || !this.\$container.is('[data-curated]')) return actions;
            \$element.data('curatedExtraAdded', true);
            return actions.concat(makeExtraActions(\$element, this));
        };
    }

    // Safety net: for chips whose menus were already built before the patch
    // landed, append our items via a fresh addActionsToChip call. Deferred
    // past picker init via setTimeout(0).
    setTimeout(function() {
        if (typeof Craft === 'undefined' || typeof Craft.addActionsToChip !== 'function') return;
        jQuery('.elementselect[data-curated]').each(function() {
            var picker = jQuery(this).data('elementSelect');
            if (!picker || !picker.settings || !picker.settings.sortable) return;
            if (!picker.\$elements || !picker.\$elements.length) return;
            picker.\$elements.each(function() {
                var \$chip = jQuery(this);
                if (\$chip.data('curatedExtraAdded')) return;
                \$chip.data('curatedExtraAdded', true);
                Craft.addActionsToChip(\$chip, makeExtraActions(\$chip, picker));
            });
        });
    }, 0);

    // Inline "Sort by…" select — one-shot resort of the displayed chips.
    // Confirms before applying so a misclick doesn't nuke a manual order.
    jQuery(document).off('change.curatedSort').on('change.curatedSort', '.curated-sort-select', function() {
        var \$select = jQuery(this);
        var sortKey = \$select.val();
        if (!sortKey) return;
        \$select.val('');

        if (!window.confirm({$labelSortConfirm})) return;

        var \$toolbar = \$select.closest('.curated-sort-toolbar');
        var fieldId = \$toolbar.data('field-id');
        var sourceId = \$toolbar.data('source-id');
        var siteId = \$toolbar.data('site-id');

        var \$picker = \$toolbar.nextAll('.elementselect[data-curated]').first();
        if (!\$picker.length) {
            jQuery('.elementselect[data-curated]').each(function() {
                var p = jQuery(this).data('elementSelect');
                if (p && p.settings && String(p.settings.fieldId) === String(fieldId)) {
                    \$picker = jQuery(this);
                    return false;
                }
            });
        }
        if (!\$picker.length) return;
        var picker = \$picker.data('elementSelect');
        if (!picker) return;

        var ids = [];
        picker.\$elementsContainer.find('> li').each(function() {
            var id = jQuery(this).find('> .element, > .chip').data('id');
            if (id) ids.push(id);
        });
        if (!ids.length) return;

        var data = {
            fieldId: fieldId,
            sourceId: sourceId,
            siteId: siteId,
            sortKey: sortKey,
            ids: ids,
        };
        data[Craft.csrfTokenName] = Craft.csrfTokenValue;

        jQuery.post({$sortActionUrl}, data, function(response) {
            if (!response || !response.ids) return;
            response.ids.forEach(function(id) {
                var \$li = picker.\$elementsContainer.find('> li').filter(function() {
                    return jQuery(this).find('> .element, > .chip').data('id') == id;
                });
                if (\$li.length) picker.\$elementsContainer.append(\$li);
            });
            picker.onSortChange();
        }, 'json');
    });
})();
JS;

        Craft::$app->getView()->registerJs($js);
        Craft::$app->getView()->registerCss(<<<CSS
.curated-sort-toolbar {
    margin-bottom: 14px;
}
.curated-editor-notice {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 18px;
    font-size: 12px;
    line-height: 1.4;
}
.curated-editor-notice-icon {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    line-height: 1;
}
CSS);
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        if ($value instanceof ElementQuery) {
            return $value;
        }

        $ids = $this->resolveIds($value, $element);
        $query = $this->buildQuery();

        if (!$ids) {
            $query->id = 0;
            return $query;
        }

        $query->id = $ids;
        $query->fixedOrder = true;
        return $query;
    }

    public function afterElementSave(ElementInterface $element, bool $isNew): void
    {
        $value = $element->getFieldValue($this->handle);

        $ids = match (true) {
            $value instanceof ElementQuery => is_array($value->id)
                ? array_values(array_filter(array_map('intval', $value->id)))
                : [],
            is_array($value) => array_values(array_filter(array_map('intval', $value))),
            default => null,
        };

        if ($ids !== null) {
            Plugin::getInstance()->curated->saveOrder(
                $this->id,
                $element->id,
                $element->siteId,
                $ids
            );
        }

        parent::afterElementSave($element, $isNew);
    }

    /**
     * @return array<string,string> [class => display name]
     */
    private function elementTypeOptions(): array
    {
        $registered = Craft::$app->getElements()->getAllElementTypes();
        $types = [];
        foreach (self::SUPPORTED_TYPES as $class) {
            if (!in_array($class, $registered, true) || !class_exists($class)) {
                continue;
            }
            /** @var class-string<ElementInterface> $class */
            $types[$class] = $class::displayName();
        }
        asort($types);
        return $types;
    }

    /**
     * @return array<string,array<int,array{value:string,label:string}>>
     */
    private function sourcesByType(): array
    {
        $result = [];
        foreach (array_keys($this->elementTypeOptions()) as $class) {
            /** @var class-string<ElementInterface> $class */
            $options = [];
            foreach ($class::sources('settings') as $source) {
                if (!is_array($source) || empty($source['key']) || empty($source['label'])) {
                    continue;
                }
                if ($source['key'] === '*') {
                    continue;
                }
                $options[] = ['value' => $source['key'], 'label' => $source['label']];
            }
            $result[$class] = $options;
        }
        return $result;
    }

    private function buildQuery(): ElementQuery
    {
        /** @var class-string<ElementInterface> $class */
        $class = $this->targetElementType;
        return $class::find();
    }

    /**
     * @return int[]
     */
    private function resolveIds(mixed $value, ?ElementInterface $element): array
    {
        if (is_string($value)) {
            return array_values(array_filter(array_map('intval', explode(',', $value))));
        }
        if (is_array($value)) {
            return array_values(array_filter(array_map('intval', $value)));
        }
        if (!$element || !$element->id) {
            return [];
        }
        return Plugin::getInstance()->curated->getMergedTargetIds($this, $element);
    }
}

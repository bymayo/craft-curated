<?php

namespace bymayo\curated\fields;

use bymayo\curated\Plugin;
use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\db\ElementQuery;
use craft\helpers\Cp;
use craft\helpers\StringHelper;

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
    public const SORT_TITLE_ASC = 'titleAsc';
    public const SORT_TITLE_DESC = 'titleDesc';
    public const SORT_DATE_CREATED_DESC = 'dateCreatedDesc';
    public const SORT_DATE_CREATED_ASC = 'dateCreatedAsc';
    public const SORT_DATE_UPDATED_DESC = 'dateUpdatedDesc';
    public const SORT_RANDOM = 'random';

    public const SORT_OPTIONS = [
        self::SORT_NONE,
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

        $bundleId = 'curated-bundle-' . StringHelper::randomString(10);

        // Register OUR JS first so the chip-menu patch is in place before
        // the picker (registered next) initializes its chips.
        $this->registerFieldJs($bundleId, $this->handle);

        $pickerHtml = Cp::elementSelectHtml([
            'name' => $this->handle,
            'elementType' => $this->targetElementType,
            'sources' => $this->sources === '*' ? null : $this->sources,
            'elements' => $elements,
            'sortable' => true,
            'viewMode' => $this->viewMode,
            'showSiteMenu' => true,
            // Marker on the picker's container itself so the chip-menu patch
            // can detect this is a Curated field without depending on
            // surrounding HTML being preserved by Craft's field rendering.
            'containerAttributes' => [
                'data' => ['curated' => '1'],
            ],
        ]);

        // Bundle every chip ID into a single JSON-encoded hidden input so we
        // submit one input regardless of list size. Chips remain in the DOM
        // (with name="<handle>[]") for the picker UI but get disabled by the
        // JS below, so they don't count toward PHP's max_input_vars.
        $initialIds = array_values(array_map(fn($e) => (int)$e->id, $elements));
        $bundleValue = htmlspecialchars(json_encode($initialIds), ENT_QUOTES);
        $handle = htmlspecialchars($this->handle, ENT_QUOTES);

        $bundleHtml = sprintf(
            '<input type="hidden" name="%s" id="%s" value="%s">',
            $handle,
            htmlspecialchars($bundleId, ENT_QUOTES),
            $bundleValue
        );

        // Marker on the wrapper so our chip-menu patch can detect Curated
        // fields and only add its items there.
        return sprintf(
            '<div class="curated-field-wrapper" data-curated="1">%s%s</div>',
            $pickerHtml,
            $bundleHtml
        );
    }

    /**
     * Wires up two pieces of behavior on the picker:
     *   1. Bundle chip IDs into the JSON hidden input on every change, so we
     *      submit a single input regardless of size (sidesteps max_input_vars).
     *   2. Patch Craft's element-select chip menu to add Move to top / bottom /
     *      position N alongside Craft's native Move up / Move down.
     */
    private function registerFieldJs(string $bundleId, string $handle): void
    {
        $jsHandle = json_encode($handle);
        $jsBundleId = json_encode($bundleId);
        $labelTop = json_encode(Craft::t('curated', 'Move to top'));
        $labelBottom = json_encode(Craft::t('curated', 'Move to bottom'));
        $labelPosition = json_encode(Craft::t('curated', 'Move to position…'));
        $labelPrompt = json_encode(Craft::t('curated', 'Move to position (1 to {total}):'));

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

    // Patch defineElementActions for any chips added LATER (e.g. via the
    // "Add an element" button), so the patched method is in place once
    // picker.addElements() runs for them.
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

    // Safety net: for chips whose menus were ALREADY built before the patch
    // landed (or who were rendered server-side), add our items via a fresh
    // addActionsToChip call. Defer past picker init via setTimeout(0).
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

    // Bundle chip IDs into the JSON hidden input so we submit a single input
    // (sidesteps PHP's max_input_vars on large lists).
    var bundle = document.getElementById({$jsBundleId});
    if (!bundle) return;
    var wrapper = bundle.parentNode;
    if (!wrapper) return;
    var handle = {$jsHandle};
    var chipInputSelector = 'input[type="hidden"][name\$="' + handle + '[]"]';

    function sync() {
        var inputs = wrapper.querySelectorAll(chipInputSelector);
        var ids = [];
        inputs.forEach(function(input) {
            input.disabled = true;
            if (input.value) ids.push(input.value);
        });
        bundle.value = JSON.stringify(ids);
    }

    new MutationObserver(sync).observe(wrapper, {
        childList: true,
        subtree: true,
        attributes: true,
    });
    sync();
})();
JS;

        Craft::$app->getView()->registerJs($js);
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
            $trimmed = trim($value);
            // Bundled JSON from getInputHtml's hidden input.
            if ($trimmed !== '' && $trimmed[0] === '[') {
                $decoded = json_decode($trimmed, true);
                if (is_array($decoded)) {
                    return array_values(array_filter(array_map('intval', $decoded)));
                }
            }
            // Legacy comma-separated form.
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

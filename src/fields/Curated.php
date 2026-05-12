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

        $pickerHtml = Cp::elementSelectHtml([
            'name' => $this->handle,
            'elementType' => $this->targetElementType,
            'sources' => $this->sources === '*' ? null : $this->sources,
            'elements' => $elements,
            'sortable' => true,
            'viewMode' => $this->viewMode,
            'showSiteMenu' => true,
        ]);

        // Bundle every chip ID into a single JSON-encoded hidden input so we
        // submit one input regardless of list size. Chips remain in the DOM
        // (with name="<handle>[]") for the picker UI but get disabled by the
        // JS below, so they don't count toward PHP's max_input_vars.
        $bundleId = 'curated-bundle-' . StringHelper::randomString(10);
        $initialIds = array_values(array_map(fn($e) => (int)$e->id, $elements));
        $bundleValue = htmlspecialchars(json_encode($initialIds), ENT_QUOTES);
        $handle = htmlspecialchars($this->handle, ENT_QUOTES);

        $bundleHtml = sprintf(
            '<input type="hidden" name="%s" id="%s" value="%s">',
            $handle,
            htmlspecialchars($bundleId, ENT_QUOTES),
            $bundleValue
        );

        $this->registerFieldJs($bundleId, $this->handle);
        $this->registerFieldCss();

        return $pickerHtml . $bundleHtml;
    }

    /**
     * Wires up two pieces of behavior on the picker:
     *   1. Bundle chip IDs into the JSON hidden input on every change, so we
     *      submit a single input regardless of size (sidesteps max_input_vars).
     *   2. Inject a quick-action menu on each chip with Move to Top / Bottom /
     *      Position N — useful when drag-reorder is impractical on long lists.
     */
    private function registerFieldJs(string $bundleId, string $handle): void
    {
        $jsHandle = json_encode($handle);
        $jsBundleId = json_encode($bundleId);
        $labelHeader = json_encode(Craft::t('curated', 'Reorder'));
        $labelUp = json_encode(Craft::t('curated', 'Move up'));
        $labelDown = json_encode(Craft::t('curated', 'Move down'));
        $labelTop = json_encode(Craft::t('curated', 'Move to top'));
        $labelBottom = json_encode(Craft::t('curated', 'Move to bottom'));
        $labelPosition = json_encode(Craft::t('curated', 'Move to position…'));
        $labelQuickReorder = json_encode(Craft::t('curated', 'Reorder'));
        $labelPrompt = json_encode(Craft::t('curated', 'Move to position (1 to {total}):'));

        $js = <<<JS
(function() {
    var bundle = document.getElementById({$jsBundleId});
    if (!bundle) return;
    var wrapper = bundle.parentNode;
    if (!wrapper) return;
    var handle = {$jsHandle};
    var chipInputSelector = 'input[type="hidden"][name\$="' + handle + '[]"]';

    function chipFor(input) {
        return input.closest('.chip') || input.closest('.element') || input.parentElement;
    }

    function getChipsInOrder() {
        var inputs = wrapper.querySelectorAll(chipInputSelector);
        var chips = [];
        inputs.forEach(function(input) {
            var chip = chipFor(input);
            if (chip && chips.indexOf(chip) === -1) chips.push(chip);
        });
        return chips;
    }

    function moveBy(chip, delta) {
        var chips = getChipsInOrder();
        var idx = chips.indexOf(chip);
        if (idx === -1) return;
        var target = idx + delta;
        if (target < 0 || target >= chips.length) return;
        var parent = chip.parentElement;
        if (!parent) return;
        if (delta > 0) {
            parent.insertBefore(chip, chips[target].nextSibling);
        } else {
            parent.insertBefore(chip, chips[target]);
        }
    }
    function moveToTop(chip) {
        var chips = getChipsInOrder();
        if (!chips.length) return;
        chip.parentElement.insertBefore(chip, chips[0]);
    }
    function moveToBottom(chip) {
        var chips = getChipsInOrder();
        if (!chips.length) return;
        chip.parentElement.appendChild(chip);
    }
    function moveToPosition(chip, pos) {
        var chips = getChipsInOrder().filter(function(c) { return c !== chip; });
        var index = Math.max(0, Math.min(pos - 1, chips.length));
        var parent = chip.parentElement;
        if (!parent) return;
        if (index >= chips.length) {
            parent.appendChild(chip);
        } else {
            parent.insertBefore(chip, chips[index]);
        }
    }

    function closeAllMenus() {
        wrapper.querySelectorAll('.curated-action-menu').forEach(function(m) {
            m.style.display = 'none';
        });
    }

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.curated-action-toolbar') && !e.target.closest('.curated-action-menu')) {
            closeAllMenus();
        }
    });

    function buildMenu(chip) {
        var menu = document.createElement('div');
        menu.className = 'curated-action-menu';
        menu.style.display = 'none';

        var header = document.createElement('div');
        header.className = 'curated-action-header';
        header.textContent = {$labelHeader};
        menu.appendChild(header);

        function addItem(label, action) {
            var item = document.createElement('button');
            item.type = 'button';
            item.className = 'curated-action-item';
            item.textContent = label;
            item.addEventListener('click', function(e) {
                e.stopPropagation();
                e.preventDefault();
                closeAllMenus();
                action();
            });
            menu.appendChild(item);
        }

        addItem({$labelTop}, function() { moveToTop(chip); });
        addItem({$labelBottom}, function() { moveToBottom(chip); });
        addItem({$labelPosition}, function() {
            var total = getChipsInOrder().length;
            var promptText = {$labelPrompt}.replace('{total}', total);
            var input = window.prompt(promptText, '1');
            if (input === null) return;
            var pos = parseInt(input, 10);
            if (!isNaN(pos)) moveToPosition(chip, pos);
        });

        return menu;
    }

    function makeBtn(label, glyph, onClick) {
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'curated-action-btn';
        btn.innerHTML = glyph;
        btn.title = label;
        btn.setAttribute('aria-label', label);
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            e.preventDefault();
            onClick(e);
        });
        return btn;
    }

    function ensureActions(chip) {
        if (chip.querySelector(':scope > .curated-action-toolbar')) return;

        var toolbar = document.createElement('div');
        toolbar.className = 'curated-action-toolbar';

        var up = makeBtn({$labelUp}, '&uarr;', function() { moveBy(chip, -1); });
        var down = makeBtn({$labelDown}, '&darr;', function() { moveBy(chip, 1); });

        var menu = buildMenu(chip);
        var more = makeBtn({$labelQuickReorder}, '&#x21C5;', function() {
            var isOpen = menu.style.display === 'block';
            closeAllMenus();
            if (!isOpen) menu.style.display = 'block';
        });

        toolbar.appendChild(up);
        toolbar.appendChild(down);
        toolbar.appendChild(more);
        chip.appendChild(toolbar);
        chip.appendChild(menu);
    }

    function sync() {
        var inputs = wrapper.querySelectorAll(chipInputSelector);
        var ids = [];
        inputs.forEach(function(input) {
            input.disabled = true;
            if (input.value) ids.push(input.value);
        });
        bundle.value = JSON.stringify(ids);

        getChipsInOrder().forEach(ensureActions);
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

    private function registerFieldCss(): void
    {
        $css = <<<CSS
.chip, .element {
    position: relative;
}
.curated-action-toolbar {
    display: inline-flex;
    gap: 2px;
    margin-right: 6px;
    padding-right: 6px;
    border-right: 1px solid var(--hairline-color, rgba(96, 125, 159, 0.25));
    vertical-align: middle;
}
.chip > .curated-action-toolbar,
.element > .curated-action-toolbar {
    position: absolute;
    top: 50%;
    left: 4px;
    transform: translateY(-50%);
    margin-right: 0;
    padding-right: 0;
    border-right: 0;
    background: var(--gray-050, rgba(255, 255, 255, 0.9));
    border-radius: 4px;
    padding: 2px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.08);
}
.curated-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    padding: 0;
    border: 0;
    background: transparent;
    color: var(--text-color, inherit);
    cursor: pointer;
    opacity: 0.7;
    font-size: 13px;
    line-height: 1;
    border-radius: 3px;
}
.curated-action-btn:hover,
.curated-action-btn:focus {
    opacity: 1;
    background: rgba(0, 0, 0, 0.08);
    outline: none;
}
.curated-action-menu {
    position: absolute;
    top: 100%;
    left: 4px;
    margin-top: 2px;
    z-index: 100;
    min-width: 180px;
    background: var(--white, #fff);
    border: 1px solid var(--hairline-color, rgba(96, 125, 159, 0.25));
    border-radius: 4px;
    box-shadow: 0 6px 18px rgba(0, 0, 0, 0.12);
    padding: 4px 0;
}
.curated-action-header {
    padding: 6px 12px 4px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--medium-text-color, #687684);
    border-bottom: 1px solid var(--hairline-color, rgba(0, 0, 0, 0.08));
    margin-bottom: 4px;
}
.curated-action-item {
    display: block;
    width: 100%;
    padding: 6px 12px;
    border: 0;
    background: transparent;
    text-align: left;
    cursor: pointer;
    font-size: 13px;
    color: var(--text-color, inherit);
}
.curated-action-item:hover,
.curated-action-item:focus {
    background: var(--gray-100, rgba(0, 0, 0, 0.05));
    outline: none;
}
CSS;

        Craft::$app->getView()->registerCss($css);
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

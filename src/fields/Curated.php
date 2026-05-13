<?php

namespace bymayo\curated\fields;

use bymayo\curated\Plugin;
use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\elements\db\ElementQuery;
use craft\helpers\Cp;
use craft\helpers\Gql as GqlHelper;
use GraphQL\Type\Definition\Type;

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
class Curated extends Field implements PreviewableFieldInterface
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

    /** One of 'list', 'list-inline', 'cards', 'cards-grid'. Matches Craft's BaseRelationField constants. */
    public string $viewMode = 'list';

    public function init(): void
    {
        parent::init();
        // Normalize legacy view-mode values from earlier builds.
        if ($this->viewMode === 'large') {
            $this->viewMode = 'list-inline';
        } elseif ($this->viewMode === 'cardsGrid') {
            $this->viewMode = 'cards-grid';
        }
    }

    /** Custom label for the "Add an element" button. Null = Craft's default. */
    public ?string $selectionLabel = null;

    /**
     * Show the "Add an element" button on the picker. Off by default —
     * Curated is an ordering layer over native relations; adding via the
     * picker creates a curated-only row that doesn't show up in
     * `relatedTo()` queries. Enable to allow that behavior anyway.
     */
    public bool $allowAdd = false;

    /**
     * Session key prefix for pinned-IDs captured in normalizeValue.
     *
     * Saving a drafted entry fires multiple HTTP requests: one that carries
     * the `__pinned` POST data and saves the provisional draft, then a
     * follow-up that applies the draft to the canonical without re-passing
     * POST. A PHP-static stash doesn't survive between those requests, so
     * the pinned state lives in the user's session, keyed by field ID and
     * canonical element ID (drafts and their canonical share that ID).
     */
    private const PIN_SESSION_PREFIX = 'curated:pin:';

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
    public const SORT_PRICE_ASC = 'priceAsc';
    public const SORT_PRICE_DESC = 'priceDesc';

    public const SORT_OPTIONS = [
        self::SORT_NONE,
        self::SORT_PLACE_AT_TOP,
        self::SORT_TITLE_ASC,
        self::SORT_TITLE_DESC,
        self::SORT_DATE_CREATED_DESC,
        self::SORT_DATE_CREATED_ASC,
        self::SORT_DATE_UPDATED_DESC,
        self::SORT_RANDOM,
        self::SORT_PRICE_ASC,
        self::SORT_PRICE_DESC,
    ];

    /** Element classes that support price sort. */
    private const PRICE_SORT_TYPES = [
        'craft\\commerce\\elements\\Product',
        'craft\\commerce\\elements\\Variant',
    ];

    private function supportsPriceSort(): bool
    {
        return in_array($this->targetElementType, self::PRICE_SORT_TYPES, true);
    }

    /**
     * Default "Add" button label for the current target type. Mirrors what
     * Craft's native relation fields return from their static
     * `defaultSelectionLabel()` methods — using the same 'app' translation
     * category so the strings come from Craft's existing translations.
     */
    private function defaultSelectionLabel(): string
    {
        return match ($this->targetElementType) {
            'craft\\elements\\Asset' => Craft::t('app', 'Add an asset'),
            'craft\\elements\\Category' => Craft::t('app', 'Add a category'),
            'craft\\elements\\Entry' => Craft::t('app', 'Add an entry'),
            'craft\\elements\\User' => Craft::t('app', 'Add a user'),
            'craft\\commerce\\elements\\Product' => Craft::t('app', 'Add a product'),
            'craft\\commerce\\elements\\Variant' => Craft::t('app', 'Add a variant'),
            default => Craft::t('app', 'Choose'),
        };
    }

    public static function displayName(): string
    {
        return 'Curated';
    }

    /**
     * Icon shown next to the field-type name in the field picker.
     * Bundled with the plugin so it renders without depending on Craft's
     * built-in icon names. Lives in `src/` next to `icon.svg`.
     */
    public static function icon(): string
    {
        return __DIR__ . '/../icon-field.svg';
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
            [['viewMode'], 'in', 'range' => ['list', 'list-inline', 'cards', 'cards-grid']],
            [['initialSort'], 'in', 'range' => self::SORT_OPTIONS],
            [['sources'], 'safe'],
            [['selectionLabel'], 'string'],
            [['allowAdd'], 'boolean'],
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

    /**
     * Legacy shim — earlier builds stored `showCardsInGrid` as its own bool.
     * It's now folded into `viewMode` as the `cardsGrid` value. Translate
     * old settings on load: if it was `true`, bump viewMode to 'cardsGrid'.
     */
    public function setShowCardsInGrid(mixed $value): void
    {
        if ($value && $this->viewMode === 'cards') {
            $this->viewMode = 'cards-grid';
        }
    }

    /** Legacy shim — silently absorb removed settings. */
    public function setShowUnpermittedSections(mixed $value): void
    {
    }

    public function setShowUnpermittedEntries(mixed $value): void
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
            'viewModePickerHtml' => $this->renderViewModePicker(),
            'initialSortOptions' => $this->initialSortOptions(),
        ]);
    }

    /**
     * Options for the Default Placement select. Price options are only
     * included when the target is a Commerce Product or Variant.
     *
     * @return array<int,array{value:string,label:string}>
     */
    private function initialSortOptions(): array
    {
        $options = [
            ['value' => self::SORT_NONE, 'label' => Craft::t('curated', 'After other elements')],
            ['value' => self::SORT_PLACE_AT_TOP, 'label' => Craft::t('curated', 'Before other elements')],
            ['value' => self::SORT_TITLE_ASC, 'label' => Craft::t('curated', 'Title (A–Z)')],
            ['value' => self::SORT_TITLE_DESC, 'label' => Craft::t('curated', 'Title (Z–A)')],
            ['value' => self::SORT_DATE_CREATED_DESC, 'label' => Craft::t('curated', 'Date created (newest first)')],
            ['value' => self::SORT_DATE_CREATED_ASC, 'label' => Craft::t('curated', 'Date created (oldest first)')],
            ['value' => self::SORT_DATE_UPDATED_DESC, 'label' => Craft::t('curated', 'Date updated (newest first)')],
            ['value' => self::SORT_RANDOM, 'label' => Craft::t('curated', 'Random')],
        ];

        if ($this->supportsPriceSort()) {
            $options[] = ['value' => self::SORT_PRICE_ASC, 'label' => Craft::t('curated', 'Price (low to high)')];
            $options[] = ['value' => self::SORT_PRICE_DESC, 'label' => Craft::t('curated', 'Price (high to low)')];
        }

        return $options;
    }

    private function renderViewModePicker(): string
    {
        $bundle = Craft::$app->getView()->registerAssetBundle(\craft\web\assets\cp\CpAsset::class);
        $iconsUrl = $bundle->baseUrl . '/images/view-modes';

        $modes = [
            'list' => Craft::t('curated', 'List'),
            'list-inline' => Craft::t('curated', 'Inline list'),
            'cards' => Craft::t('curated', 'Cards'),
            'cards-grid' => Craft::t('curated', 'Card grid'),
        ];

        $html = \craft\helpers\Html::beginTag('div', ['class' => ['flex', 'items-start', 'gap-l']]);
        foreach ($modes as $key => $label) {
            $html .= \craft\helpers\Html::beginTag('label', ['class' => 'nowrap'])
                . \craft\helpers\Html::img("$iconsUrl/$key.svg", [
                    'class' => 'mb-xs',
                    'width' => $key === 'list' ? 48 : 80,
                    'height' => 60,
                    'alt' => '',
                ])
                . \craft\helpers\Html::radio('viewMode', $key === $this->viewMode, ['value' => $key])
                . ' ' . \craft\helpers\Html::encode($label)
                . \craft\helpers\Html::endTag('label');
        }
        $html .= \craft\helpers\Html::endTag('div');

        return \craft\helpers\Cp::fieldHtml($html, [
            'label' => Craft::t('curated', 'View Mode'),
            'instructions' => Craft::t('curated', 'Choose how the field should look for authors.'),
            'id' => 'viewMode',
        ]);
    }

    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        $elements = $value instanceof ElementQuery
            ? $value->status(null)->all()
            : [];

        $this->registerFieldJs();

        $pickerConfig = [
            'name' => $this->handle,
            'elementType' => $this->targetElementType,
            'sources' => $this->sources === '*' ? null : $this->sources,
            'elements' => $elements,
            'sortable' => true,
            'viewMode' => $this->viewMode,
            'showSiteMenu' => true,
            'fieldId' => $this->id,
            'allowAdd' => $this->allowAdd,
            // Marker on the picker's container so the chip-menu JS patch can
            // detect this is a Curated field and only add its extra items
            // here, not in other element pickers across the CP.
            'containerAttributes' => [
                'data' => ['curated' => '1'],
            ],
        ];

        $pickerConfig['selectionLabel'] = $this->selectionLabel !== null && $this->selectionLabel !== ''
            ? Craft::t('site', $this->selectionLabel)
            : $this->defaultSelectionLabel();

        // Skip rendering the picker entirely when there's nothing to show
        // and no Add button to expose, so the field collapses to just the
        // toolbar instead of leaving a 30+px empty drop zone behind.
        $pickerHtml = (empty($elements) && !$this->allowAdd)
            ? ''
            : Cp::elementSelectHtml($pickerConfig);

        // Pinned IDs ride along in a hidden input named `<handle>[__pinned]`
        // so Craft's namespaceInputs treats it as a sub-key of the field's
        // POST array, alongside the chip IDs. Then normalizeValue sees
        // `__pinned` as a key inside the value array and can capture it.
        $canonicalId = $element ? (int)($element->getCanonicalId() ?? $element->id) : 0;
        $pinnedIds = ($canonicalId > 0 && $this->id)
            ? Plugin::getInstance()->curated->getPinnedIds($this->id, $canonicalId, $element->siteId)
            : [];

        $pinnedHtml = sprintf(
            '<input type="hidden" name="%s[__pinned]" class="curated-pinned-input" value="%s">',
            htmlspecialchars($this->handle, ENT_QUOTES),
            htmlspecialchars(implode(',', $pinnedIds), ENT_QUOTES)
        );

        return $this->renderSortToolbar($element) . $pickerHtml . $pinnedHtml . $this->renderEditorNotice();
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

        $options = [
            ['value' => '', 'label' => Craft::t('curated', 'Sort by')],
            ['value' => self::SORT_TITLE_ASC, 'label' => Craft::t('curated', 'Title (A–Z)')],
            ['value' => self::SORT_TITLE_DESC, 'label' => Craft::t('curated', 'Title (Z–A)')],
            ['value' => self::SORT_DATE_CREATED_DESC, 'label' => Craft::t('curated', 'Date created (newest first)')],
            ['value' => self::SORT_DATE_CREATED_ASC, 'label' => Craft::t('curated', 'Date created (oldest first)')],
            ['value' => self::SORT_DATE_UPDATED_DESC, 'label' => Craft::t('curated', 'Date updated (newest first)')],
            ['value' => self::SORT_RANDOM, 'label' => Craft::t('curated', 'Random')],
        ];

        if ($this->supportsPriceSort()) {
            $options[] = ['value' => self::SORT_PRICE_ASC, 'label' => Craft::t('curated', 'Price (low to high)')];
            $options[] = ['value' => self::SORT_PRICE_DESC, 'label' => Craft::t('curated', 'Price (high to low)')];
        }

        $selectHtml = Cp::selectHtml([
            'inputAttributes' => [
                'class' => 'curated-sort-select',
            ],
            'options' => $options,
            'value' => '',
        ]);

        $searchHtml = sprintf(
            '<div class="texticon search icon clearable curated-search"><input type="search" class="text fullwidth curated-search-input" placeholder="%s" autocomplete="off"></div>',
            htmlspecialchars(Craft::t('curated', 'Search'), ENT_QUOTES)
        );

        return sprintf(
            '<div class="curated-sort-toolbar" data-field-id="%d" data-source-id="%d" data-site-id="%d">%s%s</div>',
            $fieldId,
            $sourceId,
            $siteId,
            $searchHtml,
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
        $labelPin = json_encode(Craft::t('curated', 'Pin'));
        $labelUnpin = json_encode(Craft::t('curated', 'Unpin'));
        $sortActionUrl = json_encode(\craft\helpers\UrlHelper::actionUrl('curated/sort/run'));

        // Inline thumbtack SVG so the marker renders in Craft 5 where the
        // legacy `data-icon` font glyph system is gone. Source: Font Awesome
        // (same path Craft itself ships for the `thumbtack` icon).
        $pinSvg = json_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 384 512" aria-hidden="true" focusable="false">' .
            '<path fill="currentColor" d="M306.5 186.6l-5.7-42.6H328c13.3 0 24-10.7 24-24V24c0-13.3-10.7-24-24-24H56C42.7 0 32 10.7 32 24v96c0 13.3 10.7 24 24 24h27.2l-5.7 42.6C28.9 207.4 0 244.5 0 287.6c0 16.4 13.3 29.7 29.7 29.7H160v161.6c0 1.6.4 3.2 1.1 4.6l24 48c4 8.1 15.7 8.1 19.7 0l24-48c.7-1.4 1.1-3 1.1-4.6V317.4h130.3c16.4 0 29.7-13.3 29.7-29.7 0-43.1-28.9-80.2-83.4-100.9z"/>' .
            '</svg>'
        );

        $js = <<<JS
(function() {
    function pinnedInputFor(picker) {
        return picker.\$container.closest('.field, .input').first().find('.curated-pinned-input').first();
    }
    function pinnedIds(picker) {
        var input = pinnedInputFor(picker);
        if (!input.length) return [];
        var v = (input.val() || '').trim();
        if (!v) return [];
        return v.split(',').map(function(s) { return parseInt(s, 10); }).filter(function(n) { return !isNaN(n); });
    }
    // Whenever a reorder happens (drag, Move up/down, Move to top/bottom/
    // position N, pin/unpin, Craft's native actions), pin items should
    // always lead. This normalizes the DOM so anything non-pinned that
    // drifted above a pinned item gets pushed back below, and updates the
    // hidden input so the new pinned order (if any pinned items were
    // reshuffled among themselves) is persisted to the DB on save.
    var enforcing = false;
    function enforcePinnedOrder(picker) {
        if (enforcing) return;
        var ids = pinnedIds(picker);
        if (!ids.length) return;
        enforcing = true;
        try {
            var set = {};
            ids.forEach(function(id) { set[id] = true; });
            var container = picker.\$elementsContainer;
            var pinnedRows = [];
            var unpinnedRows = [];
            container.find('> li').each(function() {
                var \$li = jQuery(this);
                var id = parseInt(\$li.find('> .element, > .chip').first().data('id'), 10);
                if (set[id]) pinnedRows.push(\$li);
                else unpinnedRows.push(\$li);
            });
            // Re-append in pinned-first order. Same parent → moves nodes.
            pinnedRows.concat(unpinnedRows).forEach(function(\$li) {
                container.append(\$li);
            });
            // Persist the (potentially reshuffled) pinned order to the input.
            var input = pinnedInputFor(picker);
            input.val(pinnedRows.map(function(\$li) {
                return parseInt(\$li.find('> .element, > .chip').first().data('id'), 10);
            }).join(','));
        } finally {
            enforcing = false;
        }
    }
    function setPinned(picker, ids) {
        var input = pinnedInputFor(picker);
        input.val(ids.join(','));
        var set = {};
        ids.forEach(function(id) { set[id] = true; });
        picker.\$elementsContainer.find('> li').each(function() {
            var \$li = jQuery(this);
            var id = parseInt(\$li.find('> .element, > .chip').data('id'), 10);
            var pinned = !!set[id];
            \$li.toggleClass('curated-pinned', pinned);
            // Pin icon sits in `.chip-actions`, before the drag-handle dots.
            var \$actions = \$li.find('.chip-actions').first();
            if (!\$actions.length) return;
            var \$marker = \$actions.find('.curated-pin-marker');
            if (pinned && !\$marker.length) {
                jQuery('<span class="curated-pin-marker" aria-hidden="true">' + {$pinSvg} + '</span>').prependTo(\$actions);
            } else if (!pinned && \$marker.length) {
                \$marker.remove();
            }
        });
    }
    function pinChip(picker, \$element) {
        var id = parseInt(\$element.data('id'), 10);
        if (isNaN(id)) return;
        var ids = pinnedIds(picker);
        if (ids.indexOf(id) === -1) ids.push(id);
        setPinned(picker, ids);
        var \$row = \$element.closest('li');
        picker.\$elementsContainer.prepend(\$row);
        picker.onSortChange();
    }
    function unpinChip(picker, \$element) {
        var id = parseInt(\$element.data('id'), 10);
        if (isNaN(id)) return;
        var ids = pinnedIds(picker).filter(function(n) { return n !== id; });
        setPinned(picker, ids);
        picker.onSortChange();
    }

    function makeExtraActions(\$element, picker) {
        var container = picker.\$elementsContainer;
        function \$li() { return \$element.closest('li'); }
        var id = parseInt(\$element.data('id'), 10);
        var initiallyPinned = pinnedIds(picker).indexOf(id) !== -1;

        return [{
            // `icon` and `iconHtml` aren't honored for items added through
            // defineElementActions, so leave them blank and inject the SVG
            // ourselves after the menu opens (see scanMenusForPinIcon below).
            label: initiallyPinned ? {$labelUnpin} : {$labelPin},
            callback: function() {
                // Re-check current pin state on click — the menu is built
                // once and could be stale by the time the editor clicks.
                if (pinnedIds(picker).indexOf(id) !== -1) {
                    unpinChip(picker, \$element);
                } else {
                    pinChip(picker, \$element);
                }
            },
        }, {
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

    // Initial pinned-class application on existing chips. Runs once per
    // page; idempotent thereafter.
    setTimeout(function() {
        jQuery('.elementselect[data-curated]').each(function() {
            var picker = jQuery(this).data('elementSelect');
            if (picker) setPinned(picker, pinnedIds(picker));
        });
    }, 0);

    // Inject the pin SVG into Pin/Unpin menu items as they appear in the DOM.
    // Items added via defineElementActions don't honor `icon` / `iconHtml`,
    // so we match by label text and prepend the SVG to the existing button.
    var PIN_LABEL = {$labelPin};
    var UNPIN_LABEL = {$labelUnpin};
    function decorateMenu(\$menu) {
        \$menu.find('button, a').each(function() {
            var \$btn = jQuery(this);
            if (\$btn.data('curatedPinDecorated')) return;
            var text = jQuery.trim(\$btn.text());
            if (text !== PIN_LABEL && text !== UNPIN_LABEL) return;
            \$btn.data('curatedPinDecorated', true);
            \$btn.find('.curated-menu-pin-icon').remove();
            jQuery('<span class="menu-item-icon curated-menu-pin-icon" aria-hidden="true">' + {$pinSvg} + '</span>')
                .prependTo(\$btn);
        });
    }
    // Re-apply the pinned class/marker on any Curated pickers that get
    // (re-)rendered after the initial page load — e.g. when Craft replaces
    // the field's HTML in-place after a save.
    function reinitPickerPins(\$picker) {
        // Defer a tick so any Garnish init has finished attaching the
        // BaseElementSelectInput instance.
        setTimeout(function() {
            var picker = \$picker.data('elementSelect');
            if (picker) setPinned(picker, pinnedIds(picker));
        }, 0);
    }
    if (window.MutationObserver) {
        var menuObserver = new MutationObserver(function(mutations) {
            for (var i = 0; i < mutations.length; i++) {
                var added = mutations[i].addedNodes;
                for (var j = 0; j < added.length; j++) {
                    var node = added[j];
                    if (!node || node.nodeType !== 1) continue;
                    var \$node = jQuery(node);
                    if (\$node.hasClass('menu') || \$node.hasClass('menu--disclosure')) decorateMenu(\$node);
                    \$node.find('.menu, .menu--disclosure').each(function() { decorateMenu(jQuery(this)); });
                    // Curated pickers freshly inserted into the DOM (e.g.
                    // after an in-place save re-render) need their pinned
                    // chips re-styled.
                    if (\$node.is('.elementselect[data-curated]')) reinitPickerPins(\$node);
                    \$node.find('.elementselect[data-curated]').each(function() {
                        reinitPickerPins(jQuery(this));
                    });
                }
            }
        });
        menuObserver.observe(document.body, { childList: true, subtree: true });
    }
    // Decorate any menus already in the DOM at script start.
    jQuery('.menu, .menu--disclosure').each(function() { decorateMenu(jQuery(this)); });

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

        // After any reorder (drag, Move up/down, custom actions, pin/unpin),
        // snap pinned items back to the top of the list and pull any
        // non-pinned item that ended up above a pinned one down to where it
        // belongs.
        var origOnSortChange = Craft.BaseElementSelectInput.prototype.onSortChange;
        Craft.BaseElementSelectInput.prototype.onSortChange = function() {
            if (this.\$container && this.\$container.is('[data-curated]')) {
                enforcePinnedOrder(this);
            }
            return origOnSortChange ? origOnSortChange.apply(this, arguments) : undefined;
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

    // Client-side search filter on the picker. Matches against chips'
    // `data-label` (set by Cp::baseElementAttributes) so it covers titles
    // and any element with a UI label. Hides non-matches; order is preserved.
    jQuery(document).off('input.curatedSearch').on('input.curatedSearch', '.curated-search-input', function() {
        var query = (this.value || '').trim().toLowerCase();
        var \$toolbar = jQuery(this).closest('.curated-sort-toolbar');
        var fieldId = \$toolbar.data('field-id');

        var \$picker = jQuery();
        jQuery('.elementselect[data-curated]').each(function() {
            var p = jQuery(this).data('elementSelect');
            if (p && p.settings && String(p.settings.fieldId) === String(fieldId)) {
                \$picker = jQuery(this);
                return false;
            }
        });
        if (!\$picker.length) return;

        \$picker.find('> ul > li, > .elements > li').each(function() {
            var \$li = jQuery(this);
            var label = (\$li.find('[data-label]').attr('data-label') || '').toLowerCase();
            \$li.toggle(query === '' || label.indexOf(query) !== -1);
        });
    });
})();
JS;

        Craft::$app->getView()->registerJs($js);
        Craft::$app->getView()->registerCss(<<<CSS
.curated-sort-toolbar {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
}
.curated-search {
    flex: 0 0 200px;
    max-width: 200px;
    margin: 0;
}
.curated-sort-toolbar .select {
    max-width: 200px;
}
.curated-editor-notice {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-top: 6px;
    font-size: 12px;
    line-height: 1.4;
}
.curated-editor-notice-icon {
    flex: 0 0 auto;
    display: inline-flex;
    align-items: center;
    line-height: 1;
}
.curated-pin-marker {
    display: inline-flex;
    align-items: center;
    margin-right: 6px;
    color: var(--link-color, #2c5cdb);
    cursor: default;
}
.curated-pin-marker svg {
    width: 12px;
    height: 12px;
}
.curated-pinned > .element,
.curated-pinned > .chip {
    background-color: color-mix(in srgb, var(--link-color, #2c5cdb) 8%, transparent);
}
.curated-menu-pin-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    align-self: center;
    width: 14px;
    height: 14px;
    margin-right: 10px;
    color: inherit;
    flex-shrink: 0;
    vertical-align: middle;
}
.curated-menu-pin-icon svg {
    width: 14px;
    height: 14px;
    display: block;
}
CSS);
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        if ($value instanceof ElementQuery) {
            return $value;
        }

        // The hidden pinned input submits as `fields[handle][__pinned]`
        // alongside chip IDs at numeric keys. Stash the value in the user's
        // session (keyed by field ID + canonical element ID) so both the
        // draft save and the canonical save — separate requests — can read
        // the same value. Strip the key before resolving chip IDs.
        if (is_array($value) && array_key_exists('__pinned', $value)) {
            $canonicalId = $this->pinSessionCanonicalId($element);
            if ($canonicalId > 0) {
                $sessionKey = self::PIN_SESSION_PREFIX . $this->id . ':' . $canonicalId;
                Craft::$app->getSession()->set($sessionKey, (string)$value['__pinned']);
            }
            unset($value['__pinned']);
            $value = array_values($value);
        }

        $ids = $this->resolveIds($value, $element);
        $query = $this->buildQuery();

        if (!$ids) {
            $query->id = 0;
            return $query;
        }

        $query->id = $ids;
        $query->fixedOrder = true;
        Plugin::getInstance()->curated->applySources($this, $query);
        return $query;
    }

    /**
     * Element-index preview. Reuses Craft's own `Cp::elementPreviewHtml()`,
     * which renders the first item as a chip and overflows the rest into a
     * "+N" pill that pops a list on click — same behavior Craft's native
     * relation field columns use.
     */
    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        if (!$value instanceof ElementQuery) {
            return '';
        }
        $elements = $value->status(null)->all();
        if (!$elements) {
            return '';
        }
        return Cp::elementPreviewHtml($elements);
    }

    public function previewPlaceholderHtml(mixed $value, ?ElementInterface $element): string
    {
        $targetClass = $this->targetElementType;
        if (!$targetClass || !class_exists($targetClass)) {
            return '';
        }
        /** @var class-string<ElementInterface> $targetClass */
        $mockup = new $targetClass();
        $mockup->title = Craft::t('curated', 'Curated {type}', [
            'type' => $targetClass::displayName(),
        ]);
        return Cp::chipHtml($mockup);
    }

    /**
     * GraphQL output type for the field — a list of the target element type's
     * interface, with the standard query arguments for that type (limit,
     * offset, status, search, etc.). The resolver returns the saved curated
     * order, with any provided arguments applied to the query before fetch.
     */
    public function getContentGqlType(): Type|array
    {
        [$interfaceType, $args] = $this->resolveGqlInterfaceAndArgs();

        if ($interfaceType === null) {
            // Target type is unavailable (e.g. Commerce uninstalled) — fall
            // back to a list of IDs so the schema still loads.
            return Type::listOf(Type::id());
        }

        $fieldHandle = $this->handle;
        return [
            'name' => $fieldHandle,
            'type' => Type::listOf($interfaceType),
            'args' => $args,
            'resolve' => function ($source, array $arguments) use ($fieldHandle) {
                if (!$source instanceof ElementInterface) {
                    return [];
                }
                $query = $source->getFieldValue($fieldHandle);
                if (!$query instanceof ElementQuery) {
                    return [];
                }
                foreach ($arguments as $key => $value) {
                    if ($value === null) {
                        continue;
                    }
                    if (property_exists($query, $key) || method_exists($query, $key)) {
                        $query->$key = $value;
                    }
                }
                return $query->all();
            },
            'complexity' => GqlHelper::eagerLoadComplexity(),
        ];
    }

    /**
     * GraphQL mutation input — accepts a list of element IDs in the desired
     * curated order. Same shape as Craft's native relation fields.
     */
    public function getContentGqlMutationArgumentType(): Type|array
    {
        return [
            'name' => $this->handle,
            'type' => Type::listOf(Type::id()),
            'description' => $this->instructions . ' Accepts an array of element IDs, in the desired curated order.',
        ];
    }

    /**
     * Pairs each supported target element type with its GraphQL interface
     * + query-argument classes. Commerce types are only included when the
     * Commerce plugin is installed.
     *
     * @return array{0:?Type,1:array}
     */
    private function resolveGqlInterfaceAndArgs(): array
    {
        $map = [
            \craft\elements\Asset::class => [\craft\gql\interfaces\elements\Asset::class, \craft\gql\arguments\elements\Asset::class],
            \craft\elements\Category::class => [\craft\gql\interfaces\elements\Category::class, \craft\gql\arguments\elements\Category::class],
            \craft\elements\Entry::class => [\craft\gql\interfaces\elements\Entry::class, \craft\gql\arguments\elements\Entry::class],
            \craft\elements\User::class => [\craft\gql\interfaces\elements\User::class, \craft\gql\arguments\elements\User::class],
        ];

        $commerceProductInterface = 'craft\\commerce\\gql\\interfaces\\elements\\Product';
        $commerceProductArgs = 'craft\\commerce\\gql\\arguments\\elements\\Product';
        if (class_exists($commerceProductInterface)) {
            $map['craft\\commerce\\elements\\Product'] = [$commerceProductInterface, $commerceProductArgs];
        }

        $commerceVariantInterface = 'craft\\commerce\\gql\\interfaces\\elements\\Variant';
        $commerceVariantArgs = 'craft\\commerce\\gql\\arguments\\elements\\Variant';
        if (class_exists($commerceVariantInterface)) {
            $map['craft\\commerce\\elements\\Variant'] = [$commerceVariantInterface, $commerceVariantArgs];
        }

        if (!isset($map[$this->targetElementType])) {
            return [null, []];
        }

        [$interfaceClass, $argsClass] = $map[$this->targetElementType];
        return [$interfaceClass::getType(), $argsClass::getArguments()];
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
            $service = Plugin::getInstance()->curated;

            // Use the canonical element's ID as the source — drafts share
            // their curated state with the canonical so reloading after a
            // save doesn't lose pins or ordering.
            $sourceId = (int)($element->getCanonicalId() ?? $element->id);

            // If the "remove native relations" setting is on, find chips that
            // dropped out of the curated order on this save and delete the
            // matching native relation rows. Auto-discovery picks them up
            // again otherwise.
            if (Plugin::getInstance()->getSettings()->removeNativeRelations) {
                $previousIds = array_map('intval', $service->getTargetIds(
                    $this->id,
                    $sourceId,
                    $element->siteId
                ));
                $previousMerged = $service->getMergedTargetIds($this, $element);
                $beforeIds = array_unique(array_merge($previousIds, $previousMerged));
                $removed = array_diff($beforeIds, $ids);
                foreach ($removed as $targetId) {
                    $service->deleteNativeRelations($sourceId, (int)$targetId);
                }
            }

            // Pinned IDs ride along in the hidden `__pinned` sub-key of the
            // field's POST array. Captured in normalizeValue into the user's
            // session, keyed by field ID + canonical element ID, so saves
            // that span multiple requests (draft → apply-to-canonical) all
            // see the same value. The session entry is cleared once the
            // canonical save has consumed it.
            $pinnedIds = [];
            $sessionKey = $sourceId > 0 ? self::PIN_SESSION_PREFIX . $this->id . ':' . $sourceId : null;
            $session = Craft::$app->getSession();
            $raw = $sessionKey !== null ? $session->get($sessionKey) : null;

            // Craft fires a cascade of internal afterElementSave calls after a
            // user save — including ones with no POST body for this field. If
            // we have no fresh pin data from this request, preserve the
            // existing DB pinned state instead of wiping it.
            $fieldInPost = false;
            $request = Craft::$app->getRequest();
            if (!$request->getIsConsoleRequest()) {
                $bodyFields = $request->getBodyParam('fields', []);
                $fieldInPost = is_array($bodyFields) && isset($bodyFields[$this->handle]);
            }
            if (($raw === null || $raw === '') && !$fieldInPost) {
                $existingPinned = $service->getPinnedIds($this->id, $sourceId, $element->siteId);
                $raw = implode(',', $existingPinned);
            }

            if ($raw !== null && $raw !== '') {
                $pinnedIds = array_values(array_filter(array_map('intval', explode(',', (string)$raw))));
                // Only persist pins that actually exist in the saved order.
                $idSet = array_flip($ids);
                $pinnedIds = array_values(array_filter($pinnedIds, fn($id) => isset($idSet[$id])));
            }

            $service->saveOrder(
                $this->id,
                $sourceId,
                $element->siteId,
                $ids,
                $pinnedIds
            );

            // Intentionally NOT clearing the session entry here. Craft fires
            // additional internal saves after the canonical with no POST body
            // for this field; if the session were empty when those run, they
            // would wipe the just-written pin state. The stash is naturally
            // overwritten by the next user save (via normalizeValue) and
            // disappears with the user's session.
        }

        parent::afterElementSave($element, $isNew);
    }

    /**
     * Resolve the canonical element ID for use as a session key segment.
     * Drafts return their canonical's ID; canonicals return their own.
     * Returns 0 when no element is available (e.g. validation contexts).
     */
    private function pinSessionCanonicalId(?ElementInterface $element): int
    {
        if (!$element) {
            return 0;
        }
        $canonicalId = method_exists($element, 'getCanonicalId') ? $element->getCanonicalId() : null;
        return (int)($canonicalId ?? $element->id ?? 0);
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

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

        $this->registerBundleJs($bundleId, $this->handle);

        return $pickerHtml . $bundleHtml;
    }

    /**
     * Bundles all chip inputs into a single JSON hidden input before submit,
     * so large curated lists don't get clipped by PHP's max_input_vars.
     */
    private function registerBundleJs(string $bundleId, string $handle): void
    {
        $jsHandle = json_encode($handle);
        $jsBundleId = json_encode($bundleId);

        $js = <<<JS
(function() {
    var bundle = document.getElementById({$jsBundleId});
    if (!bundle) return;
    var wrapper = bundle.parentNode;
    if (!wrapper) return;
    var handle = {$jsHandle};
    // Namespacing prefixes names but never appends, so the chip names always
    // end with `<handle>[]`. The bundle input is excluded by `:not(#…)`.
    var selector = 'input[type="hidden"][name\$="' + handle + '[]"]';

    function sync() {
        var inputs = wrapper.querySelectorAll(selector);
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

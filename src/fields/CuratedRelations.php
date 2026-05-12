<?php

namespace bymayo\curate\fields;

use bymayo\curate\Plugin;
use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\elements\db\ElementQuery;
use craft\helpers\Cp;

/**
 * Curated Relations field
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
class CuratedRelations extends Field
{
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
        return 'Curated Relations';
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

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('curate/_field/settings', [
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

        return Cp::elementSelectHtml([
            'name' => $this->handle,
            'elementType' => $this->targetElementType,
            'sources' => $this->sources === '*' ? null : $this->sources,
            'elements' => $elements,
            'sortable' => true,
            'viewMode' => $this->viewMode,
            'showSiteMenu' => true,
        ]);
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
            Plugin::getInstance()->curate->saveOrder(
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
        $types = [];
        foreach (Craft::$app->getElements()->getAllElementTypes() as $class) {
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
        return Plugin::getInstance()->curate->getTargetIds(
            $this->id,
            $element->id,
            $element->siteId
        );
    }
}

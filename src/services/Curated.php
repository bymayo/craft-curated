<?php

namespace bymayo\curated\services;

use bymayo\curated\fields\Curated as CuratedField;
use bymayo\curated\records\CuratedRelation;
use Craft;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\helpers\StringHelper;
use yii\base\Component;

/**
 * Curated service
 *
 * Owns the read/write contract for the {{%curated_relations}} table.
 * All writes flow through this service so future imports, console
 * commands, and event handlers share one code path.
 */
class Curated extends Component
{
    /**
     * Return the curated order from the join table only (no native merge).
     *
     * @return int[]
     */
    public function getTargetIds(int $fieldId, int $sourceId, ?int $sourceSiteId): array
    {
        return CuratedRelation::find()
            ->select(['targetId'])
            ->where([
                'fieldId' => $fieldId,
                'sourceId' => $sourceId,
                'sourceSiteId' => $sourceSiteId,
            ])
            ->orderBy(['pinned' => SORT_DESC, 'sortOrder' => SORT_ASC])
            ->column();
    }

    /**
     * IDs that are pinned for a given (field, source, site).
     *
     * @return int[]
     */
    public function getPinnedIds(int $fieldId, int $sourceId, ?int $sourceSiteId): array
    {
        return array_map('intval', CuratedRelation::find()
            ->select(['targetId'])
            ->where([
                'fieldId' => $fieldId,
                'sourceId' => $sourceId,
                'sourceSiteId' => $sourceSiteId,
                'pinned' => true,
            ])
            ->orderBy(['sortOrder' => SORT_ASC])
            ->column());
    }

    /**
     * Merge of pinned curated + ordered curated + auto-discovered natives.
     * Pinned IDs always lead. Within pinned and curated groups, saved order
     * is honored. Newly-discovered natives respect Default Placement.
     *
     * @return int[]
     */
    public function getMergedTargetIds(CuratedField $field, ElementInterface $parent): array
    {
        // Drafts share their curated state with their canonical — look up by
        // canonical ID so a freshly-created provisional draft sees the
        // canonical's pins and order.
        $parentId = (int)($parent->getCanonicalId() ?? $parent->id);
        $allCurated = array_map('intval', $this->getTargetIds($field->id, $parentId, $parent->siteId));
        $pinnedIds = $this->getPinnedIds($field->id, $parentId, $parent->siteId);
        $pinnedSet = array_flip($pinnedIds);

        // Curated rows minus pinned (so pinned aren't double-counted).
        $curated = [];
        foreach ($allCurated as $id) {
            if (!isset($pinnedSet[$id])) {
                $curated[] = $id;
            }
        }

        $native = $this->getNativeRelatedIds($field, $parent);
        $seen = array_flip(array_merge($pinnedIds, $curated));
        $newNatives = [];
        foreach ($native as $id) {
            if (!isset($seen[$id])) {
                $newNatives[] = $id;
                $seen[$id] = true;
            }
        }

        $body = $field->initialSort === CuratedField::SORT_PLACE_AT_TOP
            ? array_merge($newNatives, $curated)
            : array_merge($curated, $newNatives);

        return array_merge($pinnedIds, $body);
    }

    /**
     * Every element of $field's target type that has any native relation to
     * $parent, in either direction. Honors the field's `initialSort`.
     *
     * @return int[]
     */
    public function getNativeRelatedIds(CuratedField $field, ElementInterface $parent): array
    {
        $targetClass = $field->targetElementType;
        if (!$targetClass || !class_exists($targetClass)) {
            return [];
        }
        /** @var class-string<ElementInterface> $targetClass */
        $query = $targetClass::find()
            ->status(null)
            ->siteId($parent->siteId)
            ->relatedTo($parent);

        $this->applySources($field, $query);
        $this->applyInitialSort($query, $field->initialSort);

        return array_map('intval', $query->ids());
    }

    /**
     * Apply the field's `sources` setting to a query so elements outside the
     * configured sources are excluded. Reads each source's native criteria
     * (e.g. `['sectionId' => 5]`) from the target element class's
     * `::sources('settings')` declaration, merges them by criteria key, and
     * applies them via ElementQuery property assignment.
     *
     * Idempotent for `'*'` (all sources). For element types whose sources
     * use uncommon criteria keys (multiple per source), only the per-key
     * union is applied.
     */
    public function applySources(CuratedField $field, ElementQuery $query): void
    {
        if ($field->sources === '*' || !is_array($field->sources) || empty($field->sources)) {
            return;
        }

        $targetClass = $field->targetElementType;
        if (!$targetClass || !class_exists($targetClass)) {
            return;
        }

        /** @var class-string<ElementInterface> $targetClass */
        $allSources = $targetClass::sources('settings');
        $combined = [];

        foreach ($allSources as $source) {
            if (!is_array($source) || !isset($source['key'], $source['criteria'])) {
                continue;
            }
            if (!in_array($source['key'], $field->sources, true)) {
                continue;
            }
            foreach ((array)$source['criteria'] as $key => $value) {
                $combined[$key] = $combined[$key] ?? [];
                if (is_array($value)) {
                    $combined[$key] = array_merge($combined[$key], $value);
                } else {
                    $combined[$key][] = $value;
                }
            }
        }

        foreach ($combined as $key => $values) {
            $values = array_values(array_unique($values));
            $query->$key = count($values) === 1 ? $values[0] : $values;
        }
    }

    /**
     * One-shot sort for the editor's inline "Sort by" control. Returns the
     * given IDs reordered per the chosen sort key. Items whose target row
     * can't be resolved (deleted, soft-deleted, etc.) get appended in their
     * original position at the end, so a Sort never silently drops chips.
     *
     * @param int[] $ids
     * @return int[]
     */
    public function sortIds(CuratedField $field, ?ElementInterface $parent, string $sortKey, array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }

        $targetClass = $field->targetElementType;
        if (!$targetClass || !class_exists($targetClass)) {
            return $ids;
        }

        /** @var class-string<ElementInterface> $targetClass */
        $query = $targetClass::find()
            ->status(null)
            ->siteId($parent?->siteId ?? '*')
            ->id($ids);

        $this->applyInitialSort($query, $sortKey);

        $sorted = array_map('intval', $query->ids());

        // Re-append any IDs missing from the query result, preserving caller order.
        $seen = array_flip($sorted);
        foreach ($ids as $id) {
            if (!isset($seen[$id])) {
                $sorted[] = $id;
                $seen[$id] = true;
            }
        }

        return $sorted;
    }

    private function applyInitialSort(ElementQuery $query, string $sort): void
    {
        switch ($sort) {
            case CuratedField::SORT_TITLE_ASC:
                $query->orderBy(['title' => SORT_ASC]);
                break;
            case CuratedField::SORT_TITLE_DESC:
                $query->orderBy(['title' => SORT_DESC]);
                break;
            case CuratedField::SORT_DATE_CREATED_DESC:
                $query->orderBy(['dateCreated' => SORT_DESC]);
                break;
            case CuratedField::SORT_DATE_CREATED_ASC:
                $query->orderBy(['dateCreated' => SORT_ASC]);
                break;
            case CuratedField::SORT_DATE_UPDATED_DESC:
                $query->orderBy(['dateUpdated' => SORT_DESC]);
                break;
            case CuratedField::SORT_RANDOM:
                $query->orderBy(new \yii\db\Expression(
                    Craft::$app->getDb()->getIsMysql() ? 'RAND()' : 'RANDOM()'
                ));
                break;
            case CuratedField::SORT_PRICE_ASC:
            case CuratedField::SORT_PRICE_DESC:
                $direction = $sort === CuratedField::SORT_PRICE_ASC ? SORT_ASC : SORT_DESC;
                // Variants sort by their own price column directly. Products
                // sort by `defaultPrice`, which Commerce's ProductQuery
                // resolves to the default variant's price via internal joins.
                $productQuery = 'craft\\commerce\\elements\\db\\ProductQuery';
                $variantQuery = 'craft\\commerce\\elements\\db\\VariantQuery';
                if ($variantQuery && is_a($query, $variantQuery)) {
                    $query->orderBy(['price' => $direction]);
                } elseif ($productQuery && is_a($query, $productQuery)) {
                    $query->orderBy(['defaultPrice' => $direction]);
                }
                // Other element types silently fall through — no price column.
                break;
            case CuratedField::SORT_NONE:
            default:
                // Default Craft ordering — typically insertion order.
                break;
        }
    }

    /**
     * Replace the ordered list of targets for a (field, source, site).
     *
     * Caller is responsible for passing IDs in the desired order.
     *
     * @param int[] $targetIds
     */
    public function saveOrder(int $fieldId, int $sourceId, ?int $sourceSiteId, array $targetIds, array $pinnedIds = []): void
    {
        $db = Craft::$app->getDb();
        $pinnedSet = array_flip(array_map('intval', $pinnedIds));
        $transaction = $db->beginTransaction();

        try {
            $db->createCommand()
                ->delete('{{%curated_relations}}', [
                    'fieldId' => $fieldId,
                    'sourceId' => $sourceId,
                    'sourceSiteId' => $sourceSiteId,
                ])
                ->execute();

            $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
            $rows = [];
            foreach (array_values($targetIds) as $i => $targetId) {
                $rows[] = [
                    $fieldId,
                    $sourceId,
                    $sourceSiteId,
                    (int)$targetId,
                    $i + 1,
                    isset($pinnedSet[(int)$targetId]) ? 1 : 0,
                    $now,
                    $now,
                    StringHelper::UUID(),
                ];
            }

            if ($rows) {
                $db->createCommand()
                    ->batchInsert('{{%curated_relations}}', [
                        'fieldId', 'sourceId', 'sourceSiteId', 'targetId',
                        'sortOrder', 'pinned', 'dateCreated', 'dateUpdated', 'uid',
                    ], $rows)
                    ->execute();
            }

            $transaction->commit();
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    /**
     * Append a target to the end of the curated order if not already present.
     */
    public function append(int $fieldId, int $sourceId, ?int $sourceSiteId, int $targetId): void
    {
        $existing = $this->getTargetIds($fieldId, $sourceId, $sourceSiteId);
        if (in_array($targetId, $existing, true)) {
            return;
        }
        $existing[] = $targetId;
        $this->saveOrder($fieldId, $sourceId, $sourceSiteId, $existing);
    }

    /**
     * Remove a target from the curated order.
     */
    public function remove(int $fieldId, int $sourceId, ?int $sourceSiteId, int $targetId): void
    {
        $existing = $this->getTargetIds($fieldId, $sourceId, $sourceSiteId);
        $filtered = array_values(array_filter($existing, fn($id) => $id !== $targetId));
        if (count($filtered) === count($existing)) {
            return;
        }
        $this->saveOrder($fieldId, $sourceId, $sourceSiteId, $filtered);
    }

    /**
     * Drop a target from every curated list (e.g. when the element is deleted).
     */
    public function removeTargetEverywhere(int $targetId): void
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%curated_relations}}', ['targetId' => $targetId])
            ->execute();
    }

    /**
     * Delete every native relation row between $parentId and $targetId, in
     * either direction, regardless of which relation field created it.
     * Used when the `removeNativeRelations` setting is on and an editor
     * removes a chip from a Curated field.
     */
    public function deleteNativeRelations(int $parentId, int $targetId): void
    {
        Craft::$app->getDb()->createCommand()
            ->delete('{{%relations}}', [
                'or',
                ['and', ['sourceId' => $parentId, 'targetId' => $targetId]],
                ['and', ['sourceId' => $targetId, 'targetId' => $parentId]],
            ])
            ->execute();
    }

    /**
     * Snapshot any natively-related elements into the curated order so they
     * get explicit positions. Items already in the curated list are left
     * alone — re-running is safe.
     *
     * Returns the number of new items appended.
     */
    public function syncFromNativeRelations(CuratedField $field, ElementInterface $parent): int
    {
        $native = $this->getNativeRelatedIds($field, $parent);
        if (!$native) {
            return 0;
        }

        $existing = array_map('intval', $this->getTargetIds($field->id, $parent->id, $parent->siteId));
        $seen = array_flip($existing);

        $appended = 0;
        foreach ($native as $id) {
            if (!isset($seen[$id])) {
                $existing[] = $id;
                $seen[$id] = true;
                $appended++;
            }
        }

        if ($appended > 0) {
            $this->saveOrder($field->id, $parent->id, $parent->siteId, $existing);
        }

        return $appended;
    }

    /**
     * Walk every Curated field and snapshot each parent element's natives.
     *
     * @return array{appended:int,parents:int,fields:int}
     */
    public function syncAll(): array
    {
        $appended = 0;
        $parentsTouched = 0;
        $fieldsTouched = 0;

        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            if (!$field instanceof CuratedField) {
                continue;
            }
            $fieldsTouched++;

            foreach ($this->parentsForField($field) as $parent) {
                $count = $this->syncFromNativeRelations($field, $parent);
                if ($count > 0) {
                    $parentsTouched++;
                    $appended += $count;
                }
            }
        }

        return [
            'appended' => $appended,
            'parents' => $parentsTouched,
            'fields' => $fieldsTouched,
        ];
    }

    /**
     * Yield every element whose field layout contains $field.
     *
     * @return iterable<ElementInterface>
     */
    private function parentsForField(CuratedField $field): iterable
    {
        $elementsService = Craft::$app->getElements();
        foreach ($elementsService->getAllElementTypes() as $elementClass) {
            /** @var class-string<ElementInterface> $elementClass */
            $query = $elementClass::find()->status(null)->siteId('*');
            foreach ($query->each() as $element) {
                /** @var ElementInterface $element */
                if ($element->getFieldLayout()?->getFieldByHandle($field->handle)) {
                    yield $element;
                }
            }
        }
    }
}

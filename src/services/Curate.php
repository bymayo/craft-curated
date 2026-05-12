<?php

namespace bymayo\curate\services;

use bymayo\curate\records\CuratedRelation;
use Craft;
use craft\helpers\StringHelper;
use yii\base\Component;

/**
 * Curate service
 *
 * Owns the read/write contract for the {{%curate_relations}} table.
 * All writes flow through this service so future imports, console
 * commands, and event handlers share one code path.
 */
class Curate extends Component
{
    /**
     * Return ordered target IDs for a given (field, source, site).
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
            ->orderBy(['sortOrder' => SORT_ASC])
            ->column();
    }

    /**
     * Replace the ordered list of targets for a (field, source, site).
     *
     * Caller is responsible for passing IDs in the desired order.
     *
     * @param int[] $targetIds
     */
    public function saveOrder(int $fieldId, int $sourceId, ?int $sourceSiteId, array $targetIds): void
    {
        $db = Craft::$app->getDb();
        $transaction = $db->beginTransaction();

        try {
            $db->createCommand()
                ->delete('{{%curate_relations}}', [
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
                    $now,
                    $now,
                    StringHelper::UUID(),
                ];
            }

            if ($rows) {
                $db->createCommand()
                    ->batchInsert('{{%curate_relations}}', [
                        'fieldId', 'sourceId', 'sourceSiteId', 'targetId',
                        'sortOrder', 'dateCreated', 'dateUpdated', 'uid',
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
}

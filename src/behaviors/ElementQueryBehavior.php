<?php

namespace bymayo\curated\behaviors;

use bymayo\curated\fields\Curated as CuratedField;
use bymayo\curated\Plugin;
use craft\base\ElementInterface;
use craft\elements\db\ElementQuery;
use craft\events\CancelableEvent;
use yii\base\Behavior;

/**
 * Adds ->curatedBy($source, $fieldHandle) to any ElementQuery.
 *
 *   {% set products = craft.products.curatedBy(category, 'curatedProducts').all() %}
 *
 * Restricts results to the parent's curated list and returns them in that
 * order. Transparent interception of ->relatedTo() is a deliberate extension
 * point — implicit magic is great UX but a debugging headache.
 */
class ElementQueryBehavior extends Behavior
{
    public ?ElementInterface $curatedSource = null;
    public ?string $curatedFieldHandle = null;

    public function events(): array
    {
        return [
            ElementQuery::EVENT_BEFORE_PREPARE => 'beforePrepare',
        ];
    }

    public function curatedBy(ElementInterface $source, string $fieldHandle): ElementQuery
    {
        /** @var ElementQuery $owner */
        $owner = $this->owner;
        $this->curatedSource = $source;
        $this->curatedFieldHandle = $fieldHandle;
        return $owner;
    }

    public function beforePrepare(CancelableEvent $event): void
    {
        if (!$this->curatedSource || !$this->curatedFieldHandle) {
            return;
        }

        $field = $this->curatedSource->getFieldLayout()
            ?->getFieldByHandle($this->curatedFieldHandle);
        if (!$field instanceof CuratedField) {
            return;
        }

        $ids = Plugin::getInstance()->curated->getMergedTargetIds(
            $field,
            $this->curatedSource
        );

        /** @var ElementQuery $owner */
        $owner = $this->owner;

        if (!$ids) {
            $owner->id = 0;
            return;
        }

        $owner->id = $ids;
        $owner->fixedOrder = true;
    }
}

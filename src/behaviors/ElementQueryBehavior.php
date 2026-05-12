<?php

namespace bymayo\curate\behaviors;

use bymayo\curate\Plugin;
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
    public ?ElementInterface $curateSource = null;
    public ?string $curateFieldHandle = null;

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
        $this->curateSource = $source;
        $this->curateFieldHandle = $fieldHandle;
        return $owner;
    }

    public function beforePrepare(CancelableEvent $event): void
    {
        if (!$this->curateSource || !$this->curateFieldHandle) {
            return;
        }

        $field = $this->curateSource->getFieldLayout()
            ?->getFieldByHandle($this->curateFieldHandle);
        if (!$field) {
            return;
        }

        $ids = Plugin::getInstance()->curate->getTargetIds(
            $field->id,
            $this->curateSource->id,
            $this->curateSource->siteId
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

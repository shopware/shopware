<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventAction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @feature-deprecated (flag:FEATURE_NEXT_8225) tag:v6.5.0 - Will be removed in v6.5.0
 *
 * @method void                   add(EventActionEntity $entity)
 * @method void                   set(string $key, EventActionEntity $entity)
 * @method EventActionEntity[]    getIterator()
 * @method EventActionEntity[]    getElements()
 * @method EventActionEntity|null get(string $key)
 * @method EventActionEntity|null first()
 * @method EventActionEntity|null last()
 */
class EventActionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_event_action_collection';
    }

    protected function getExpectedClass(): string
    {
        return EventActionEntity::class;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Event\EventAction;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Feature;

/**
 * @deprecated tag:v6.5.0 - Will be removed in v6.5.0
 *
 * @extends EntityCollection<EventActionEntity>
 */
class EventActionCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return 'dal_event_action_collection';
    }

    protected function getExpectedClass(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedClassMessage(__CLASS__, 'v6.5.0.0')
        );

        return EventActionEntity::class;
    }
}

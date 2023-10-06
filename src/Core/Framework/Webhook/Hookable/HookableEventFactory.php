<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Hookable;

use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\BusinessEventEncoder;
use Shopware\Core\Framework\Webhook\Hookable;

/**
 * @deprecated tag:v6.6.0 - Will be internal - reason:visibility-change
 */
#[Package('core')]
class HookableEventFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly BusinessEventEncoder $eventEncoder,
        private readonly WriteResultMerger $writeResultMerger
    ) {
    }

    /**
     * @return Hookable[]
     */
    public function createHookablesFor(object $event): array
    {
        // BusinessEvent are the generic Events that get wrapped around the specific events
        // we don't want to dispatch those to the webhooks
        if ($event instanceof StorableFlow) {
            return [];
        }

        if ($event instanceof Hookable) {
            return [$event];
        }

        if ($event instanceof FlowEventAware) {
            return [
                HookableBusinessEvent::fromBusinessEvent($event, $this->eventEncoder),
            ];
        }

        if ($event instanceof EntityWrittenContainerEvent) {
            return $this->wrapEntityWrittenEvent($event);
        }

        return [];
    }

    /**
     * @return Hookable[]
     */
    private function wrapEntityWrittenEvent(EntityWrittenContainerEvent $event): array
    {
        $hookables = [];
        foreach (HookableEventCollector::HOOKABLE_ENTITIES as $entity) {
            $writtenEvent = $event->getEventByEntityName($entity);

            if (!$writtenEvent) {
                continue;
            }

            $translationEvent = $event->getEventByEntityName($entity . '_translation');

            $mergedWrittenEvent = $this->writeResultMerger->mergeWriteResults($writtenEvent, $translationEvent);

            if (!$mergedWrittenEvent) {
                continue;
            }

            $hookables[] = HookableEntityWrittenEvent::fromWrittenEvent(
                $mergedWrittenEvent
            );
        }

        return $hookables;
    }
}

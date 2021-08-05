<?php declare(strict_types=1);

namespace Shopware\Core\Content\Flow\Indexing;

use Shopware\Core\Content\Flow\FlowEntity;
use Shopware\Core\Content\Flow\FlowEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FlowPayloadSubscriber implements EventSubscriberInterface
{
    private FlowPayloadUpdater  $updater;

    public function __construct(FlowPayloadUpdater $updater)
    {
        $this->updater = $updater;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FlowEvents::FLOW_LOADED_EVENT => 'unserialize',
        ];
    }

    public function unserialize(EntityLoadedEvent $event): void
    {
        $this->indexIfNeeded($event);

        /** @var FlowEntity $entity */
        foreach ($event->getEntities() as $entity) {
            $payload = $entity->getPayload();
            if ($payload === null || !\is_string($payload)) {
                continue;
            }

            $entity->setPayload(unserialize($payload));
        }
    }

    private function indexIfNeeded(EntityLoadedEvent $event): void
    {
        $flows = [];

        /** @var FlowEntity $flow */
        foreach ($event->getEntities() as $flow) {
            if ($flow->getPayload() === null && !$flow->isInvalid()) {
                $flows[$flow->getId()] = $flow;
            }
        }

        if (!\count($flows)) {
            return;
        }

        $updated = $this->updater->update(array_keys($flows));

        foreach ($updated as $id => $entity) {
            $flows[$id]->assign($entity);
        }
    }
}

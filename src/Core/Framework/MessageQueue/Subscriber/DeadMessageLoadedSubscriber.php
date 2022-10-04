<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\MessageQueue\DeadMessage\DeadMessageEntity;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class DeadMessageLoadedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['dead_message.loaded' => ['unserialize']];
    }

    public function unserialize(EntityLoadedEvent $event): void
    {
        /** @var DeadMessageEntity $deadMessage */
        foreach ($event->getEntities() as $deadMessage) {
            if ($deadMessage->getSerializedOriginalMessage()) {
                $deadMessage->setOriginalMessage(unserialize($deadMessage->getSerializedOriginalMessage()));
            }
        }
    }
}

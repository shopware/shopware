<?php declare(strict_types=1);

namespace Shopware\Core\Content\Rule\DataAbstractionLayer;

use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Content\Rule\RuleEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RulePayloadSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            RuleEvents::RULE_LOADED_EVENT => 'unserialize',
        ];
    }

    public function unserialize(EntityLoadedEvent $event): void
    {
        /** @var RuleEntity $entity */
        foreach ($event->getEntities() as $entity) {
            if (!$entity->getPayload()) {
                continue;
            }

            $unserialized = unserialize($entity->getPayload());

            $entity->setPayload($unserialized);
        }
    }
}

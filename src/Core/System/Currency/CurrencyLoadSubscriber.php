<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.5.0 - reason:becomes-internal - EventSubscribers will become internal in v6.5.0
 */
class CurrencyLoadSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [CurrencyEvents::CURRENCY_LOADED_EVENT => 'setDefault'];
    }

    public function setDefault(EntityLoadedEvent $event): void
    {
        /** @var CurrencyEntity $entity */
        foreach ($event->getEntities() as $entity) {
            $entity->setIsSystemDefault($entity->getId() === Defaults::CURRENCY);
        }
    }
}

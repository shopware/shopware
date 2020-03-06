<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PaymentHandlerIdentifierSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'formatHandlerIdentifier',
        ];
    }

    public function formatHandlerIdentifier(EntityLoadedEvent $event): void
    {
        /** @var PaymentMethodEntity $entity */
        foreach ($event->getEntities() as $entity) {
            $explodedHandlerIdentifier = explode('\\', $entity->getHandlerIdentifier());

            if (count($explodedHandlerIdentifier) < 2) {
                $entity->setFormattedHandlerIdentifier($entity->getHandlerIdentifier());

                continue;
            }

            $formattedHandlerIdentifier = 'handler_'
                . mb_strtolower(array_shift($explodedHandlerIdentifier))
                . '_'
                . mb_strtolower(array_pop($explodedHandlerIdentifier));

            $entity->setFormattedHandlerIdentifier($formattedHandlerIdentifier);
        }
    }
}

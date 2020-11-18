<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

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

            $last = $explodedHandlerIdentifier[\count($explodedHandlerIdentifier) - 1];
            $entity->setShortName((new CamelCaseToSnakeCaseNameConverter())->normalize((string) $last));

            if (\count($explodedHandlerIdentifier) < 2) {
                $entity->setFormattedHandlerIdentifier($entity->getHandlerIdentifier());

                continue;
            }

            /** @var string|null $firstHandlerIdentifier */
            $firstHandlerIdentifier = array_shift($explodedHandlerIdentifier);
            $lastHandlerIdentifier = array_pop($explodedHandlerIdentifier);
            if ($firstHandlerIdentifier === null || $lastHandlerIdentifier === null) {
                continue;
            }

            $formattedHandlerIdentifier = 'handler_'
                . mb_strtolower($firstHandlerIdentifier)
                . '_'
                . mb_strtolower($lastHandlerIdentifier);

            $entity->setFormattedHandlerIdentifier($formattedHandlerIdentifier);
        }
    }
}

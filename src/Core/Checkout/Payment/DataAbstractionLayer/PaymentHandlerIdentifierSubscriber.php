<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\DataAbstractionLayer;

use Shopware\Core\Checkout\Payment\PaymentEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityLoadedEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

/**
 * @internal
 */
#[Package('checkout')]
class PaymentHandlerIdentifierSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            PaymentEvents::PAYMENT_METHOD_LOADED_EVENT => 'formatHandlerIdentifier',
            'payment_method.partial_loaded' => 'formatHandlerIdentifier',
        ];
    }

    public function formatHandlerIdentifier(EntityLoadedEvent $event): void
    {
        /** @var Entity $entity */
        foreach ($event->getEntities() as $entity) {
            $entity->assign([
                'shortName' => $this->getShortName($entity),
                'formattedHandlerIdentifier' => $this->getHandlerIdentifier($entity),
            ]);
        }
    }

    private function getHandlerIdentifier(Entity $entity): string
    {
        $explodedHandlerIdentifier = explode('\\', (string) $entity->get('handlerIdentifier'));

        if (\count($explodedHandlerIdentifier) < 2) {
            return $entity->get('handlerIdentifier');
        }

        /** @var string|null $firstHandlerIdentifier */
        $firstHandlerIdentifier = array_shift($explodedHandlerIdentifier);
        $lastHandlerIdentifier = array_pop($explodedHandlerIdentifier);
        if ($firstHandlerIdentifier === null || $lastHandlerIdentifier === null) {
            return '';
        }

        return 'handler_'
            . mb_strtolower($firstHandlerIdentifier)
            . '_'
            . mb_strtolower($lastHandlerIdentifier);
    }

    private function getShortName(Entity $entity): string
    {
        $explodedHandlerIdentifier = explode('\\', (string) $entity->get('handlerIdentifier'));

        $last = $explodedHandlerIdentifier[\count($explodedHandlerIdentifier) - 1];

        return (new CamelCaseToSnakeCaseNameConverter())->normalize($last);
    }
}

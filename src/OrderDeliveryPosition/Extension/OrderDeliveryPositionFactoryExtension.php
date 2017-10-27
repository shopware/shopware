<?php declare(strict_types=1);

namespace Shopware\OrderDeliveryPosition\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderDeliveryPosition\Event\OrderDeliveryPositionBasicLoadedEvent;
use Shopware\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class OrderDeliveryPositionFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OrderDeliveryPositionBasicLoadedEvent::NAME => 'orderDeliveryPositionBasicLoaded',
        ];
    }

    public function joinDependencies(
        QuerySelection $selection,
        QueryBuilder $query,
        TranslationContext $context
    ): void {
    }

    public function getDetailFields(): array
    {
        return [];
    }

    public function getBasicFields(): array
    {
        return [];
    }

    public function hydrate(
        OrderDeliveryPositionBasicStruct $orderDeliveryPosition,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function orderDeliveryPositionBasicLoaded(OrderDeliveryPositionBasicLoadedEvent $event): void
    {
    }
}

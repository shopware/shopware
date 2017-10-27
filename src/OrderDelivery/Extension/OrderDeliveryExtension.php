<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderDelivery\Event\OrderDeliveryBasicLoadedEvent;
use Shopware\OrderDelivery\Event\OrderDeliveryDetailLoadedEvent;
use Shopware\OrderDelivery\Struct\OrderDeliveryBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class OrderDeliveryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OrderDeliveryBasicLoadedEvent::NAME => 'orderDeliveryBasicLoaded',
            OrderDeliveryDetailLoadedEvent::NAME => 'orderDeliveryDetailLoaded',
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
        OrderDeliveryBasicStruct $orderDelivery,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function orderDeliveryBasicLoaded(OrderDeliveryBasicLoadedEvent $event): void
    {
    }

    public function orderDeliveryDetailLoaded(OrderDeliveryDetailLoadedEvent $event): void
    {
    }
}

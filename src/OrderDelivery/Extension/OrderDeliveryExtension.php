<?php declare(strict_types=1);

namespace Shopware\OrderDelivery\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\OrderDelivery\Event\OrderDeliveryBasicLoadedEvent;
use Shopware\OrderDelivery\Event\OrderDeliveryDetailLoadedEvent;
use Shopware\OrderDelivery\Event\OrderDeliveryWrittenEvent;
use Shopware\OrderDelivery\Struct\OrderDeliveryBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class OrderDeliveryExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OrderDeliveryBasicLoadedEvent::NAME => 'orderDeliveryBasicLoaded',
            OrderDeliveryDetailLoadedEvent::NAME => 'orderDeliveryDetailLoaded',
            OrderDeliveryWrittenEvent::NAME => 'orderDeliveryWritten',
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

    public function orderDeliveryWritten(OrderDeliveryWrittenEvent $event): void
    {
    }
}

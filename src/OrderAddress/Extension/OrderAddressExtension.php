<?php declare(strict_types=1);

namespace Shopware\OrderAddress\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\OrderAddress\Event\OrderAddressBasicLoadedEvent;
use Shopware\OrderAddress\Event\OrderAddressWrittenEvent;
use Shopware\OrderAddress\Struct\OrderAddressBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class OrderAddressExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OrderAddressBasicLoadedEvent::NAME => 'orderAddressBasicLoaded',
            OrderAddressWrittenEvent::NAME => 'orderAddressWritten',
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
        OrderAddressBasicStruct $orderAddress,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function orderAddressBasicLoaded(OrderAddressBasicLoadedEvent $event): void
    {
    }

    public function orderAddressWritten(OrderAddressWrittenEvent $event): void
    {
    }
}

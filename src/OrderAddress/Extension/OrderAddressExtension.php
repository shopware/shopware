<?php declare(strict_types=1);

namespace Shopware\OrderAddress\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderAddress\Event\OrderAddressBasicLoadedEvent;
use Shopware\OrderAddress\Struct\OrderAddressBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class OrderAddressExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OrderAddressBasicLoadedEvent::NAME => 'orderAddressBasicLoaded',
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
}

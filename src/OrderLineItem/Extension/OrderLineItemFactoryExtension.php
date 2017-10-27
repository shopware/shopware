<?php declare(strict_types=1);

namespace Shopware\OrderLineItem\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderLineItem\Event\OrderLineItemBasicLoadedEvent;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class OrderLineItemFactoryExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OrderLineItemBasicLoadedEvent::NAME => 'orderLineItemBasicLoaded',
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
        OrderLineItemBasicStruct $orderLineItem,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function orderLineItemBasicLoaded(OrderLineItemBasicLoadedEvent $event): void
    {
    }
}

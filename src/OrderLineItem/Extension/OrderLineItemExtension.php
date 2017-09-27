<?php

namespace Shopware\OrderLineItem\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\OrderLineItem\Event\OrderLineItemBasicLoadedEvent;
use Shopware\OrderLineItem\Event\OrderLineItemWrittenEvent;
use Shopware\OrderLineItem\Struct\OrderLineItemBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class OrderLineItemExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OrderLineItemBasicLoadedEvent::NAME => 'orderLineItemBasicLoaded',
            OrderLineItemWrittenEvent::NAME => 'orderLineItemWritten',
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

    public function orderLineItemWritten(OrderLineItemWrittenEvent $event): void
    {
    }
}

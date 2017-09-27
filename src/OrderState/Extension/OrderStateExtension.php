<?php declare(strict_types=1);

namespace Shopware\OrderState\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\OrderState\Event\OrderStateBasicLoadedEvent;
use Shopware\OrderState\Event\OrderStateWrittenEvent;
use Shopware\OrderState\Struct\OrderStateBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class OrderStateExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OrderStateBasicLoadedEvent::NAME => 'orderStateBasicLoaded',
            OrderStateWrittenEvent::NAME => 'orderStateWritten',
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
        OrderStateBasicStruct $orderState,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function orderStateBasicLoaded(OrderStateBasicLoadedEvent $event): void
    {
    }

    public function orderStateWritten(OrderStateWrittenEvent $event): void
    {
    }
}

<?php declare(strict_types=1);

namespace Shopware\OrderState\Extension;

use Shopware\Api\Read\FactoryExtensionInterface;
use Shopware\Api\Search\QueryBuilder;
use Shopware\Api\Search\QuerySelection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\OrderState\Event\OrderStateBasicLoadedEvent;
use Shopware\OrderState\Struct\OrderStateBasicStruct;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class OrderStateExtension implements FactoryExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OrderStateBasicLoadedEvent::NAME => 'orderStateBasicLoaded',
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
}

<?php declare(strict_types=1);

namespace Shopware\Order\Extension;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Factory\ExtensionInterface;
use Shopware\Order\Event\OrderBasicLoadedEvent;
use Shopware\Order\Event\OrderDetailLoadedEvent;
use Shopware\Order\Event\OrderWrittenEvent;
use Shopware\Order\Struct\OrderBasicStruct;
use Shopware\Search\QueryBuilder;
use Shopware\Search\QuerySelection;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

abstract class OrderExtension implements ExtensionInterface, EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            OrderBasicLoadedEvent::NAME => 'orderBasicLoaded',
            OrderDetailLoadedEvent::NAME => 'orderDetailLoaded',
            OrderWrittenEvent::NAME => 'orderWritten',
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
        OrderBasicStruct $order,
        array $data,
        QuerySelection $selection,
        TranslationContext $translation
    ): void {
    }

    public function orderBasicLoaded(OrderBasicLoadedEvent $event): void
    {
    }

    public function orderDetailLoaded(OrderDetailLoadedEvent $event): void
    {
    }

    public function orderWritten(OrderWrittenEvent $event): void
    {
    }
}

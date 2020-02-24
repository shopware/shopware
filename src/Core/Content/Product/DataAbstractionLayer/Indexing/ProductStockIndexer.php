<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @deprecated tag:v6.3.0 - Use \Shopware\Core\Content\Product\DataAbstractionLayer\StockUpdater instead
 */
class ProductStockIndexer implements IndexerInterface, EventSubscriberInterface
{
    public static function getName(): string
    {
        return 'Swag.ProductStockIndexer';
    }

    public static function getSubscribedEvents()
    {
        return [];
    }

    public function index(\DateTimeInterface $timestamp): void
    {
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        return null;
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
    }

    public function triggerChangeSet(PreWriteValidationEvent $event): void
    {
    }

    public function lineItemWritten(EntityWrittenEvent $event): void
    {
    }

    public function stateChanged(StateMachineTransitionEvent $event): void
    {
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
    }
}

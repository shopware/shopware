<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Content\Product\Events\ProductStockAlteredEvent;
use Shopware\Core\Content\Product\Subscriber\CheapestPriceAvailabilitySubscriber;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\Telemetry\IndexerMetricsInstrumentor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CheapestPriceAvailabilitySubscriber::class)]
class CheapestPriceAvailabilitySubscriberTest extends TestCase
{
    private const PRODUCT_INDEXER_OPTIONS = [
        ProductIndexer::INHERITANCE_UPDATER,
        ProductIndexer::STOCK_UPDATER,
        ProductIndexer::VARIANT_LISTING_UPDATER,
        ProductIndexer::CHILD_COUNT_UPDATER,
        ProductIndexer::MANY_TO_MANY_ID_FIELD_UPDATER,
        ProductIndexer::CATEGORY_DENORMALIZER_UPDATER,
        ProductIndexer::CHEAPEST_PRICE_UPDATER,
        ProductIndexer::RATING_AVERAGE_UPDATER,
        ProductIndexer::STREAM_UPDATER,
        ProductIndexer::SEARCH_KEYWORD_UPDATER,
    ];

    public function testSubscribedEvents(): void
    {
        static::assertSame(
            [
                ProductNoLongerAvailableEvent::class => 'onAvailabilityFlipped',
                ProductStockAlteredEvent::class => 'scheduleCheapestPriceUpdate',
            ],
            CheapestPriceAvailabilitySubscriber::getSubscribedEvents()
        );
    }

    public function testSchedulesCheapestPriceOnlyIndexingForRuntimeAvailabilityFlip(): void
    {
        $variantId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([$parentId]);

        $dispatched = null;
        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static function (object $message) use (&$dispatched): Envelope {
                $dispatched = $message;

                return new Envelope($message);
            });

        $subscriber = new CheapestPriceAvailabilitySubscriber(
            $connection,
            $this->createIndexerRegistry(),
            $messageBus
        );

        $subscriber->onAvailabilityFlipped(new ProductNoLongerAvailableEvent([$variantId], $context));
        $subscriber->scheduleCheapestPriceUpdate(new ProductStockAlteredEvent([$variantId], $context));

        static::assertInstanceOf(ProductIndexingMessage::class, $dispatched);
        static::assertSame([$parentId], $dispatched->getData());
        static::assertSame('product.indexer', $dispatched->getIndexer());
        static::assertSame($context, $dispatched->getContext());

        static::assertTrue(
            $dispatched->allow(ProductIndexer::CHEAPEST_PRICE_UPDATER),
            'The cheapest price updater must run for the scheduled message'
        );

        foreach (self::PRODUCT_INDEXER_OPTIONS as $option) {
            if ($option === ProductIndexer::CHEAPEST_PRICE_UPDATER) {
                continue;
            }

            static::assertFalse(
                $dispatched->allow($option),
                \sprintf('Updater "%s" must be skipped for the scheduled message', $option)
            );
        }
    }

    public function testIgnoresIndexerDrivenAvailabilityFlips(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new CheapestPriceAvailabilitySubscriber(
            $connection,
            $this->createIndexerRegistry(),
            $messageBus
        );

        // The product indexer recomputes the available flag during regular product
        // writes; without a following stock alteration nothing may be scheduled.
        $subscriber->onAvailabilityFlipped(
            new ProductNoLongerAvailableEvent([Uuid::randomHex()], Context::createDefaultContext())
        );
    }

    public function testIgnoresStockAlterationsWithoutAvailabilityFlip(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new CheapestPriceAvailabilitySubscriber(
            $connection,
            $this->createIndexerRegistry(),
            $messageBus
        );

        $subscriber->scheduleCheapestPriceUpdate(
            new ProductStockAlteredEvent([Uuid::randomHex()], Context::createDefaultContext())
        );
    }

    public function testOnlySchedulesForProductsContainedInTheStockAlteration(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new CheapestPriceAvailabilitySubscriber(
            $connection,
            $this->createIndexerRegistry(),
            $messageBus
        );

        $subscriber->onAvailabilityFlipped(
            new ProductNoLongerAvailableEvent([Uuid::randomHex()], Context::createDefaultContext())
        );
        $subscriber->scheduleCheapestPriceUpdate(
            new ProductStockAlteredEvent([Uuid::randomHex()], Context::createDefaultContext())
        );
    }

    public function testCollectedFlipsAreClearedAfterScheduling(): void
    {
        $variantId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([Uuid::randomHex()]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(static fn (object $message): Envelope => new Envelope($message));

        $subscriber = new CheapestPriceAvailabilitySubscriber(
            $connection,
            $this->createIndexerRegistry(),
            $messageBus
        );

        $subscriber->onAvailabilityFlipped(new ProductNoLongerAvailableEvent([$variantId], $context));
        $subscriber->scheduleCheapestPriceUpdate(new ProductStockAlteredEvent([$variantId], $context));

        // a second stock alteration without a new flip must not schedule again
        $subscriber->scheduleCheapestPriceUpdate(new ProductStockAlteredEvent([$variantId], $context));
    }

    public function testResetClearsCollectedFlips(): void
    {
        $variantId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchFirstColumn');

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new CheapestPriceAvailabilitySubscriber(
            $connection,
            $this->createIndexerRegistry(),
            $messageBus
        );

        $subscriber->onAvailabilityFlipped(new ProductNoLongerAvailableEvent([$variantId], $context));
        $subscriber->reset();
        $subscriber->scheduleCheapestPriceUpdate(new ProductStockAlteredEvent([$variantId], $context));
    }

    public function testDoesNotDispatchWhenNoLiveProductMatches(): void
    {
        $variantId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([]);

        $messageBus = $this->createMock(MessageBusInterface::class);
        $messageBus->expects($this->never())->method('dispatch');

        $subscriber = new CheapestPriceAvailabilitySubscriber(
            $connection,
            $this->createIndexerRegistry(),
            $messageBus
        );

        $subscriber->onAvailabilityFlipped(new ProductNoLongerAvailableEvent([$variantId], $context));
        $subscriber->scheduleCheapestPriceUpdate(new ProductStockAlteredEvent([$variantId], $context));
    }

    private function createIndexerRegistry(): EntityIndexerRegistry
    {
        $productIndexer = static::createStub(ProductIndexer::class);
        $productIndexer->method('getName')->willReturn('product.indexer');
        $productIndexer->method('getOptions')->willReturn(self::PRODUCT_INDEXER_OPTIONS);

        return new EntityIndexerRegistry(
            [$productIndexer],
            static::createStub(MessageBusInterface::class),
            static::createStub(EventDispatcherInterface::class),
            static::createStub(IndexerMetricsInstrumentor::class)
        );
    }
}

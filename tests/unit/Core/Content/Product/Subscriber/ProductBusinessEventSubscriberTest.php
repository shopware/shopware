<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\Events\ProductCreatedEvent;
use Shopware\Core\Content\Product\Events\ProductDeletedEvent;
use Shopware\Core\Content\Product\Events\ProductPublishedEvent;
use Shopware\Core\Content\Product\Events\ProductStockChangedEvent;
use Shopware\Core\Content\Product\Events\ProductUnpublishedEvent;
use Shopware\Core\Content\Product\Events\ProductUpdatedEvent;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\Subscriber\ProductBusinessEventSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWriteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductBusinessEventSubscriber::class)]
class ProductBusinessEventSubscriberTest extends TestCase
{
    private ProductDefinition $productDefinition;

    protected function setUp(): void
    {
        $this->productDefinition = new ProductDefinition();
        $this->productDefinition->compile($this->createMock(DefinitionInstanceRegistry::class));
    }

    public function testProductInsertDispatchesCreatedEventAndSuppressesTranslationUpdate(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        $caught = ['created' => [], 'updated' => []];
        $dispatcher->addListener(ProductCreatedEvent::class, static function (ProductCreatedEvent $event) use (&$caught): void {
            $caught['created'][] = $event->getProductId();
        });
        $dispatcher->addListener(ProductUpdatedEvent::class, static function () use (&$caught): void {
            $caught['updated'][] = true;
        });

        $subscriber->onEntityWritten(new EntityWrittenContainerEvent($context, new NestedEventCollection([
            $this->writtenEvent(
                ProductDefinition::ENTITY_NAME,
                $productId,
                ['id' => $productId, 'versionId' => Defaults::LIVE_VERSION, 'productNumber' => 'SW-1000'],
                EntityWriteResult::OPERATION_INSERT,
                $context
            ),
            $this->writtenEvent(
                ProductTranslationDefinition::ENTITY_NAME,
                ['productId' => $productId, 'languageId' => Uuid::randomHex(), 'productVersionId' => Defaults::LIVE_VERSION],
                ['productId' => $productId, 'productVersionId' => Defaults::LIVE_VERSION, 'name' => 'New product'],
                EntityWriteResult::OPERATION_INSERT,
                $context
            ),
        ]), []));

        static::assertSame([$productId], $caught['created']);
        static::assertSame([], $caught['updated'], 'translation writes of a created product must not surface as an update');
    }

    public function testProductAndTranslationWritesMergeIntoOneUpdatedEvent(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<ProductUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(ProductUpdatedEvent::class, static function (ProductUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $subscriber->onEntityWritten(new EntityWrittenContainerEvent($context, new NestedEventCollection([
            $this->writtenEvent(
                ProductDefinition::ENTITY_NAME,
                $productId,
                ['versionId' => Defaults::LIVE_VERSION, 'updatedAt' => '2024-01-01', 'price' => ['net' => 10.0]],
                EntityWriteResult::OPERATION_UPDATE,
                $context
            ),
            $this->writtenEvent(
                ProductTranslationDefinition::ENTITY_NAME,
                ['productId' => $productId, 'languageId' => Uuid::randomHex(), 'productVersionId' => Defaults::LIVE_VERSION],
                ['productId' => $productId, 'productVersionId' => Defaults::LIVE_VERSION, 'languageId' => Uuid::randomHex(), 'name' => 'Renamed'],
                EntityWriteResult::OPERATION_UPDATE,
                $context
            ),
        ]), []));

        static::assertCount(1, $caught);
        static::assertSame($productId, $caught[0]->getProductId());
        static::assertSame(['price', 'translation.name'], $caught[0]->getChangedFields());
    }

    public function testTranslationInsertForExistingProductDispatchesUpdatedEvent(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<ProductUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(ProductUpdatedEvent::class, static function (ProductUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        // a new language added to a pre-existing product: only a translation INSERT,
        // no product write in this container — must surface as a product update
        $subscriber->onEntityWritten(new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent(ProductTranslationDefinition::ENTITY_NAME, [
                new EntityWriteResult(
                    ['productId' => $productId, 'languageId' => Uuid::randomHex(), 'productVersionId' => Defaults::LIVE_VERSION],
                    ['productId' => $productId, 'productVersionId' => Defaults::LIVE_VERSION, 'languageId' => Uuid::randomHex(), 'name' => 'Translated'],
                    ProductTranslationDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], $context),
        ]), []));

        static::assertCount(1, $caught);
        static::assertSame($productId, $caught[0]->getProductId());
        static::assertSame(['translation.name'], $caught[0]->getChangedFields());
    }

    public function testTranslationDeleteForExistingProductDispatchesUpdatedEvent(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<ProductUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(ProductUpdatedEvent::class, static function (ProductUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        // a language is removed from a surviving product: only a translation DELETE,
        // no product write — removing localized data must surface as a product update so
        // consumers re-sync names/descriptions instead of keeping a stale translation
        $subscriber->onEntityWritten(new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent(ProductTranslationDefinition::ENTITY_NAME, [
                new EntityWriteResult(
                    ['productId' => $productId, 'languageId' => Uuid::randomHex(), 'productVersionId' => Defaults::LIVE_VERSION],
                    [],
                    ProductTranslationDefinition::ENTITY_NAME,
                    EntityWriteResult::OPERATION_DELETE
                ),
            ], $context),
        ]), []));

        static::assertCount(1, $caught);
        static::assertSame($productId, $caught[0]->getProductId());
        static::assertSame(['translation'], $caught[0]->getChangedFields());
    }

    public function testTranslationDeleteAlongsideProductDeleteIsSuppressed(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<ProductUpdatedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(ProductUpdatedEvent::class, static function (ProductUpdatedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        // the product itself is deleted, cascading its translation rows: product.deleted
        // already covers this, and the product row is gone, so emitting product.updated
        // would hand a webhook encoder a loader that throws productNotFound
        $subscriber->onEntityWritten(new EntityWrittenContainerEvent($context, new NestedEventCollection([
            $this->writtenEvent(
                ProductDefinition::ENTITY_NAME,
                $productId,
                ['versionId' => Defaults::LIVE_VERSION],
                EntityWriteResult::OPERATION_DELETE,
                $context
            ),
            $this->writtenEvent(
                ProductTranslationDefinition::ENTITY_NAME,
                ['productId' => $productId, 'languageId' => Uuid::randomHex(), 'productVersionId' => Defaults::LIVE_VERSION],
                [],
                EntityWriteResult::OPERATION_DELETE,
                $context
            ),
        ]), []));

        static::assertSame([], $caught, 'a translation cascade-deleted with its product must not surface as an update');
    }

    public function testStockUpdateDispatchesStockChangedWithAbsoluteValue(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        /** @var list<ProductStockChangedEvent> $stockEvents */
        $stockEvents = [];
        $dispatcher->addListener(ProductStockChangedEvent::class, static function (ProductStockChangedEvent $event) use (&$stockEvents): void {
            $stockEvents[] = $event;
        });
        /** @var list<ProductUpdatedEvent> $updatedEvents */
        $updatedEvents = [];
        $dispatcher->addListener(ProductUpdatedEvent::class, static function (ProductUpdatedEvent $event) use (&$updatedEvents): void {
            $updatedEvents[] = $event;
        });

        $subscriber->onEntityWritten($this->createProductWrittenContainer($context, new EntityWriteResult(
            $productId,
            ['versionId' => Defaults::LIVE_VERSION, 'stock' => 7],
            ProductDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        )));

        static::assertCount(1, $stockEvents);
        static::assertSame($productId, $stockEvents[0]->getProductId());
        static::assertSame(['stock' => 7], $stockEvents[0]->getStockChange());
        static::assertCount(1, $updatedEvents);
        static::assertSame(['stock'], $updatedEvents[0]->getChangedFields());
    }

    public function testNonLiveVersionWritesAreIgnored(): void
    {
        $context = Context::createDefaultContext();
        $versionId = Uuid::randomHex();

        [$subscriber, $dispatcher] = $this->createSubscriber();

        $caught = 0;
        $listener = static function () use (&$caught): void {
            ++$caught;
        };
        $dispatcher->addListener(ProductCreatedEvent::class, $listener);
        $dispatcher->addListener(ProductUpdatedEvent::class, $listener);

        $subscriber->onEntityWritten($this->createProductWrittenContainer($context, new EntityWriteResult(
            ['id' => Uuid::randomHex(), 'versionId' => $versionId],
            ['versionId' => $versionId, 'stock' => 7],
            ProductDefinition::ENTITY_NAME,
            EntityWriteResult::OPERATION_UPDATE
        )));

        static::assertSame(0, $caught);
    }

    public function testActiveFlipDispatchesPublishedEventOnlyAfterWriteSucceeds(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([$productId => '0']);

        [$subscriber, $dispatcher] = $this->createSubscriber($connection);

        $caught = ['published' => [], 'unpublished' => []];
        $dispatcher->addListener(ProductPublishedEvent::class, static function (ProductPublishedEvent $event) use (&$caught): void {
            $caught['published'][] = $event->getProductId();
        });
        $dispatcher->addListener(ProductUnpublishedEvent::class, static function () use (&$caught): void {
            $caught['unpublished'][] = true;
        });

        $writeEvent = EntityWriteEvent::create(WriteContext::createFromContext($context), [
            new UpdateCommand(
                $this->productDefinition,
                ['active' => 1],
                ['id' => Uuid::fromHexToBytes($productId), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
                $this->createExistence($productId),
                '/0'
            ),
        ]);

        $subscriber->beforeWrite($writeEvent);

        static::assertSame([], $caught['published'], 'published must not fire before the write succeeded');

        $writeEvent->success();

        static::assertSame([$productId], $caught['published']);
        static::assertSame([], $caught['unpublished']);
    }

    public function testActiveFlipSuppressedWhenProductDeletedInSameWrite(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([$productId => '0']);

        [$subscriber, $dispatcher] = $this->createSubscriber($connection);

        $caught = ['published' => [], 'unpublished' => []];
        $dispatcher->addListener(ProductPublishedEvent::class, static function (ProductPublishedEvent $event) use (&$caught): void {
            $caught['published'][] = $event->getProductId();
        });
        $dispatcher->addListener(ProductUnpublishedEvent::class, static function () use (&$caught): void {
            $caught['unpublished'][] = true;
        });

        // a sync/version-merge batch both flips active and deletes the same product — the
        // publish event must be suppressed, because the row is gone after the write and the
        // event's lazy loader would throw productNotFound when a webhook encodes it
        $writeEvent = EntityWriteEvent::create(WriteContext::createFromContext($context), [
            new UpdateCommand(
                $this->productDefinition,
                ['active' => 1],
                ['id' => Uuid::fromHexToBytes($productId), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
                $this->createExistence($productId),
                '/0'
            ),
            new DeleteCommand(
                $this->productDefinition,
                ['id' => Uuid::fromHexToBytes($productId), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
                $this->createExistence($productId)
            ),
        ]);

        $subscriber->beforeWrite($writeEvent);
        $writeEvent->success();

        static::assertSame([], $caught['published'], 'a product deleted in the same write must not emit a publish event');
        static::assertSame([], $caught['unpublished']);
    }

    public function testActiveWriteWithoutFlipStaysSilent(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturn([$productId => '1']);

        [$subscriber, $dispatcher] = $this->createSubscriber($connection);

        $caught = 0;
        $listener = static function () use (&$caught): void {
            ++$caught;
        };
        $dispatcher->addListener(ProductPublishedEvent::class, $listener);
        $dispatcher->addListener(ProductUnpublishedEvent::class, $listener);

        $writeEvent = EntityWriteEvent::create(WriteContext::createFromContext($context), [
            new UpdateCommand(
                $this->productDefinition,
                ['active' => 1],
                ['id' => Uuid::fromHexToBytes($productId), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
                $this->createExistence($productId),
                '/0'
            ),
        ]);

        $subscriber->beforeWrite($writeEvent);
        $writeEvent->success();

        static::assertSame(0, $caught);
    }

    public function testProductDeleteDispatchesDeletedEventOnlyAfterDeleteSucceeds(): void
    {
        $productId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $product = (new ProductEntity())->assign([
            'id' => $productId,
            'productNumber' => 'SW-1000',
        ]);

        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository([
            new EntitySearchResult(
                ProductEntity::class,
                1,
                new ProductCollection([$product]),
                null,
                new Criteria([$productId]),
                $context,
            ),
        ], $this->productDefinition);

        $dispatcher = new EventDispatcher();
        $subscriber = new ProductBusinessEventSubscriber(
            $this->createMock(Connection::class),
            $productRepository,
            $dispatcher
        );

        /** @var list<ProductDeletedEvent> $caught */
        $caught = [];
        $dispatcher->addListener(ProductDeletedEvent::class, static function (ProductDeletedEvent $event) use (&$caught): void {
            $caught[] = $event;
        });

        $deleteEvent = EntityDeleteEvent::create(WriteContext::createFromContext($context), [
            new DeleteCommand(
                $this->productDefinition,
                ['id' => Uuid::fromHexToBytes($productId), 'version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)],
                $this->createExistence($productId)
            ),
        ]);

        $subscriber->beforeDelete($deleteEvent);

        static::assertCount(0, $caught, 'deleted event must not fire before the delete succeeded');

        $deleteEvent->success();

        static::assertCount(1, $caught);
        static::assertSame($productId, $caught[0]->getProductId());
        static::assertSame('SW-1000', $caught[0]->getProductNumber());
        static::assertNotSame('', $caught[0]->getDeletedAt());
    }

    public function testNonLiveVersionContextDeletesAreIgnored(): void
    {
        $productId = Uuid::randomHex();
        // a draft delete runs under a non-live version context — that is the invariant
        // the subscriber guards on (the decoded primary key never carries the version)
        $context = Context::createDefaultContext()->createWithVersionId(Uuid::randomHex());

        $dispatcher = new EventDispatcher();
        // empty repository: a search would fail loudly, proving no lookup happens
        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository([], $this->productDefinition);
        $subscriber = new ProductBusinessEventSubscriber(
            $this->createMock(Connection::class),
            $productRepository,
            $dispatcher
        );

        $caught = 0;
        $dispatcher->addListener(ProductDeletedEvent::class, static function () use (&$caught): void {
            ++$caught;
        });

        $deleteEvent = EntityDeleteEvent::create(WriteContext::createFromContext($context), [
            new DeleteCommand(
                $this->productDefinition,
                ['id' => Uuid::fromHexToBytes($productId), 'version_id' => Uuid::fromHexToBytes($context->getVersionId())],
                $this->createExistence($productId)
            ),
        ]);

        $subscriber->beforeDelete($deleteEvent);
        $deleteEvent->success();

        static::assertSame(0, $caught);
    }

    /**
     * @return array{0: ProductBusinessEventSubscriber, 1: EventDispatcher}
     */
    private function createSubscriber(?Connection $connection = null): array
    {
        $dispatcher = new EventDispatcher();
        /** @var StaticEntityRepository<ProductCollection> $productRepository */
        $productRepository = new StaticEntityRepository([], $this->productDefinition);
        $subscriber = new ProductBusinessEventSubscriber(
            $connection ?? $this->createMock(Connection::class),
            $productRepository,
            $dispatcher
        );

        return [$subscriber, $dispatcher];
    }

    private function createProductWrittenContainer(Context $context, EntityWriteResult $writeResult): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent(ProductDefinition::ENTITY_NAME, [$writeResult], $context),
        ]), []);
    }

    /**
     * Builds a single-result written event over the broad primary-key type so that a
     * container mixing product (string key) and translation (composite key) writes stays
     * one homogeneous EntityWrittenEvent generic — the container's invariant generic
     * rejects a union of two distinct EntityWrittenEvent types.
     *
     * @param string|array<string, string> $primaryKey
     * @param array<string, mixed> $payload
     *
     * @return EntityWrittenEvent<string|array<string, string>>
     */
    private function writtenEvent(string $entityName, string|array $primaryKey, array $payload, string $operation, Context $context): EntityWrittenEvent
    {
        return new EntityWrittenEvent(
            $entityName,
            [new EntityWriteResult($primaryKey, $payload, $entityName, $operation)],
            $context
        );
    }

    private function createExistence(string $productId): EntityExistence
    {
        return new EntityExistence(
            ProductDefinition::ENTITY_NAME,
            ['id' => $productId],
            true,
            false,
            false,
            ['exists' => true, 'id' => $productId]
        );
    }
}

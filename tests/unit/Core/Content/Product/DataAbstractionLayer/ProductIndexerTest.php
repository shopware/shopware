<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPriceUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductCategoryDenormalizer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductStreamUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\RatingAverageUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\SearchKeywordUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\StatesUpdater;
use Shopware\Core\Content\Product\DataAbstractionLayer\VariantListingUpdater;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Stock\AbstractStockStorage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\InheritanceUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ManyToManyIdFieldUpdater;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductIndexer::class)]
class ProductIndexerTest extends TestCase
{
    public function testUpdateSkipChildCountUpdater(): void
    {
        $context = Context::createDefaultContext();
        $writtenEvent = $this->writtenEvent($context, Uuid::randomHex());
        $writtenEvent->setCloned(true);

        $indexer = $this->createIndexer(static::createStub(Connection::class), static::createStub(AbstractStockStorage::class));

        $message = $indexer->update($writtenEvent);
        static::assertNotNull($message);
        static::assertContains(ProductIndexer::CHILD_COUNT_UPDATER, $message->getSkip());
    }

    /**
     * @param array<string, mixed> $payload
     */
    #[DataProvider('inheritedFieldChangeProvider')]
    public function testUpdateRecalculatesVariantsThatInheritTheChangedField(array $payload): void
    {
        $parentId = Uuid::randomHex();
        $inheritingVariantId = Uuid::randomHex();
        $variantWithOwnValuesId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(
            [$inheritingVariantId, $variantWithOwnValuesId], // variants of the written products
            [], // parents of the written products
            [$inheritingVariantId], // variants inheriting `isCloseout` or `minPurchase`
        );

        $context = Context::createDefaultContext();
        $stockStorage = static::createMock(AbstractStockStorage::class);
        $stockStorage->expects($this->once())->method('index')->with([$parentId, $inheritingVariantId], $context);

        $this->createIndexer($connection, $stockStorage)->update($this->writtenEvent($context, $parentId, $payload));
    }

    public static function inheritedFieldChangeProvider(): \Generator
    {
        yield 'closeout switched off on the parent' => [['isCloseout' => false]];
        yield 'min. purchase raised on the parent' => [['minPurchase' => 2]];
    }

    public function testUpdateSkipsTheVariantLookupWhenNoWrittenProductHasVariants(): void
    {
        $productId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(
            [], // variants of the written products
            [], // parents of the written products
            [Uuid::randomHex()], // what the variant lookup would return
        );

        $context = Context::createDefaultContext();
        $stockStorage = static::createMock(AbstractStockStorage::class);
        $stockStorage->expects($this->once())->method('index')->with([$productId], $context);

        $this->createIndexer($connection, $stockStorage)->update($this->writtenEvent($context, $productId, ['isCloseout' => false]));
    }

    public function testUpdateSkipsTheVariantLookupForStockOnlyChanges(): void
    {
        $parentId = Uuid::randomHex();
        $variantId = Uuid::randomHex();

        $connection = static::createStub(Connection::class);
        $connection->method('fetchFirstColumn')->willReturn(
            [$variantId], // variants of the written products
            [], // parents of the written products
            [$variantId], // what the variant lookup would return
        );

        $context = Context::createDefaultContext();
        $stockStorage = static::createMock(AbstractStockStorage::class);
        $stockStorage->expects($this->once())->method('index')->with([$parentId], $context);

        $this->createIndexer($connection, $stockStorage)->update($this->writtenEvent($context, $parentId, ['stock' => 0]));
    }

    private function createIndexer(Connection $connection, AbstractStockStorage $stockStorage): ProductIndexer
    {
        return new ProductIndexer(
            static::createStub(IteratorFactory::class),
            static::createStub(EntityRepository::class),
            $connection,
            static::createStub(VariantListingUpdater::class),
            static::createStub(ProductCategoryDenormalizer::class),
            static::createStub(InheritanceUpdater::class),
            static::createStub(RatingAverageUpdater::class),
            static::createStub(SearchKeywordUpdater::class),
            static::createStub(ChildCountUpdater::class),
            static::createStub(ManyToManyIdFieldUpdater::class),
            $stockStorage,
            static::createStub(EventDispatcher::class),
            static::createStub(CheapestPriceUpdater::class),
            static::createStub(ProductStreamUpdater::class),
            static::createStub(MessageBusInterface::class),
            Feature::isActive('v6.8.0.0') ? null : static::createStub(StatesUpdater::class),
            new NativeClock()
        );
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writtenEvent(Context $context, string $productId, array $payload = []): EntityWrittenContainerEvent
    {
        return new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent(
                ProductDefinition::ENTITY_NAME,
                [new EntityWriteResult($productId, $payload, ProductDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE)],
                $context
            ),
        ]), []);
    }
}

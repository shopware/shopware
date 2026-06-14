<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserBuilder;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductDescriptionTeaserIndexer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Util\HtmlSanitizer;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProductDescriptionTeaserIndexer::class)]
class ProductDescriptionTeaserIndexerTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('product.description_teaser.indexer', $this->createIndexer()->getName());
    }

    public function testUpdateLeavesLiveWritesToSubscriber(): void
    {
        $event = new EntityWrittenContainerEvent(Context::createDefaultContext(), new NestedEventCollection(), []);

        static::assertNull($this->createIndexer()->update($event));
    }

    public function testIterateReturnsMessageWithFetchedIds(): void
    {
        $query = $this->createMock(IterableQuery::class);
        $query->method('fetch')->willReturn(['id-1', 'id-2']);
        $query->method('getOffset')->willReturn(['offset' => 50]);

        $factory = $this->createMock(IteratorFactory::class);
        $factory->method('createIterator')->willReturn($query);

        $message = $this->createIndexer(iteratorFactory: $factory)->iterate(null);

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame(['id-1', 'id-2'], $message->getData());
        static::assertSame(['offset' => 50], $message->getOffset());
    }

    public function testIterateReturnsNullWhenNoMoreIds(): void
    {
        $query = $this->createMock(IterableQuery::class);
        $query->method('fetch')->willReturn([]);

        $factory = $this->createMock(IteratorFactory::class);
        $factory->method('createIterator')->willReturn($query);

        static::assertNull($this->createIndexer(iteratorFactory: $factory)->iterate(null));
    }

    public function testHandleOnlyUpdatesRowsWhoseTeaserDiffers(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            // already correct -> must be skipped
            [
                'product_id' => 'bytes-a',
                'product_version_id' => 'bytes-v',
                'language_id' => 'bytes-l',
                'description' => '<p>Hello <strong>World</strong></p>',
                'description_teaser' => 'Hello World',
            ],
            // drifted -> must be rewritten with the freshly stripped value
            [
                'product_id' => 'bytes-b',
                'product_version_id' => 'bytes-v',
                'language_id' => 'bytes-l',
                'description' => '<p>Foo Bar</p>',
                'description_teaser' => 'stale',
            ],
        ]);

        $updates = [];
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$updates): int {
                $updates[] = $params;

                return 1;
            });

        $this->createIndexer(connection: $connection)->handle(new EntityIndexingMessage([Uuid::randomHex()]));

        static::assertCount(1, $updates);
        static::assertSame('Foo Bar', $updates[0]['teaser']);
        static::assertSame('bytes-b', $updates[0]['productId']);
    }

    public function testHandleReconcilesTeaserToNullWhenDescriptionCleared(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([
            [
                'product_id' => 'bytes-a',
                'product_version_id' => 'bytes-v',
                'language_id' => 'bytes-l',
                'description' => null,
                'description_teaser' => 'orphaned teaser',
            ],
        ]);

        $captured = null;
        $connection->expects($this->once())
            ->method('executeStatement')
            ->willReturnCallback(function (string $sql, array $params) use (&$captured): int {
                $captured = $params;

                return 1;
            });

        $this->createIndexer(connection: $connection)->handle(new EntityIndexingMessage([Uuid::randomHex()]));

        static::assertNotNull($captured);
        static::assertNull($captured['teaser']);
    }

    public function testHandleIgnoresEmptyMessage(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->never())->method('fetchAllAssociative');
        $connection->expects($this->never())->method('executeStatement');

        $this->createIndexer(connection: $connection)->handle(new EntityIndexingMessage([]));
    }

    public function testGetTotalCountsProducts(): void
    {
        $query = $this->createMock(IterableQuery::class);
        $query->method('fetchCount')->willReturn(42);

        $factory = $this->createMock(IteratorFactory::class);
        $factory->method('createIterator')->willReturn($query);

        static::assertSame(42, $this->createIndexer(iteratorFactory: $factory)->getTotal());
    }

    public function testGetDecoratedThrows(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->createIndexer()->getDecorated();
    }

    private function createIndexer(
        ?IteratorFactory $iteratorFactory = null,
        ?Connection $connection = null
    ): ProductDescriptionTeaserIndexer {
        return new ProductDescriptionTeaserIndexer(
            $iteratorFactory ?? $this->createMock(IteratorFactory::class),
            $connection ?? $this->createMock(Connection::class),
            new ProductDescriptionTeaserBuilder(
                new HtmlSanitizer(null, false, [], [ProductDescriptionTeaserBuilder::TEASER_FIELD => ['sets' => []]])
            )
        );
    }
}

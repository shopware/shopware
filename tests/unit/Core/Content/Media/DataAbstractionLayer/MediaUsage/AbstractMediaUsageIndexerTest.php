<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\AbstractMediaUsageIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\CmsSlotMediaIdResolver;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AbstractMediaUsageIndexer::class)]
class AbstractMediaUsageIndexerTest extends TestCase
{
    private Connection&Stub $connection;

    /**
     * @var list<array{string, array<int|string, mixed>}> the executed statements and their parameters
     */
    private array $statements = [];

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('transactional')->willReturnCallback(fn (\Closure $closure) => $closure($this->connection));
        $this->connection->method('executeStatement')->willReturnCallback(function (string $sql, array $params): int {
            $this->statements[] = [$sql, $params];

            return 1;
        });
    }

    public function testIteratesTheIndexedEntityForAFullReindex(): void
    {
        $id = Uuid::randomHex();
        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('fetch')->willReturnOnConsecutiveCalls([Uuid::fromHexToBytes($id) => $id], []);
        $iterator->method('getOffset')->willReturn(['offset' => 1]);
        $iterator->method('fetchCount')->willReturn(1);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->exactly(3))->method('createIterator')->with('media_usage_test')->willReturn($iterator);

        $indexer = $this->createIndexer($iteratorFactory);
        $message = $indexer->iterate(null);

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame([$id], $message->getData());
        static::assertSame(['offset' => 1], $message->getOffset());
        static::assertNull($indexer->iterate($message->getOffset()), 'the iteration ends without entities');
        static::assertSame(1, $indexer->getTotal());
    }

    public function testReplacesTheUsagesOfTheLiveVersions(): void
    {
        $id = Uuid::randomBytes();
        $row = ['media_usage_test_id' => $id, 'media_usage_test_version_id' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION), 'media_id' => Uuid::randomBytes()];

        $this->createIndexer()->replace([$id], [$row]);

        [$delete, $insert] = $this->statements;
        static::assertStringContainsString('DELETE FROM `media_usage_test_usage` WHERE `media_usage_test_id` IN (:ids) AND `media_usage_test_version_id` = :version', $delete[0]);
        static::assertSame(['ids' => [$id], 'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION)], $delete[1]);
        static::assertSame(array_values($row), array_values($insert[1]));
    }

    public function testReadsTheIdsOfTheMessageAsBytes(): void
    {
        $id = Uuid::randomHex();
        $indexer = $this->createIndexer();

        static::assertSame([Uuid::fromHexToBytes($id)], $indexer->ids(new EntityIndexingMessage([$id, $id])), 'every id is read once');
        static::assertSame([], $indexer->ids(new EntityIndexingMessage($id)));
    }

    public function testReadsTheIdsOfTheEntitiesFromTheKeysOfTheirTranslations(): void
    {
        $id = Uuid::randomHex();

        $ids = $this->createIndexer()->ownerIds([
            ['ownerId' => $id, 'languageId' => Uuid::randomHex()],
            ['ownerId' => $id, 'languageId' => Uuid::randomHex()],
        ], 'ownerId');

        static::assertSame([$id], $ids, 'the entity of several translations is read once');
    }

    private function createIndexer(?IteratorFactory $iteratorFactory = null): TestMediaUsageIndexer
    {
        return new TestMediaUsageIndexer($iteratorFactory ?? static::createStub(IteratorFactory::class), $this->connection, new CmsSlotMediaIdResolver([]));
    }
}

/**
 * @internal
 */
class TestMediaUsageIndexer extends AbstractMediaUsageIndexer
{
    public function getName(): string
    {
        return 'media_usage_test_usage.indexer';
    }

    public function update(EntityWrittenContainerEvent $event): ?EntityIndexingMessage
    {
        return null;
    }

    public function handle(EntityIndexingMessage $message): void
    {
    }

    public function getDecorated(): EntityIndexer
    {
        return $this;
    }

    /**
     * @param list<string> $ids
     * @param list<array<string, string>> $rows
     */
    public function replace(array $ids, array $rows): void
    {
        $this->replaceUsages($ids, $rows);
    }

    /**
     * @return list<string>
     */
    public function ids(EntityIndexingMessage $message): array
    {
        return $this->getIds($message);
    }

    /**
     * @param list<array<string, string>|string> $primaryKeys
     *
     * @return list<string>
     */
    public function ownerIds(array $primaryKeys, string $ownerKey): array
    {
        return $this->getOwnerIds($primaryKeys, $ownerKey);
    }

    protected function getEntityName(): string
    {
        return 'media_usage_test';
    }

    protected function getTableName(): string
    {
        return 'media_usage_test_usage';
    }
}

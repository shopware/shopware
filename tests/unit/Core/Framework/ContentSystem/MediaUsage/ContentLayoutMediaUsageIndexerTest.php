<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\MediaUsage;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\DataLoaderConfigSerializerProvider;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredElementCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Codec\StoredTreeCodec;
use Shopware\Core\Framework\ContentSystem\Layout\Entity\ContentLayoutDefinition;
use Shopware\Core\Framework\ContentSystem\MediaUsage\ContentLayoutMediaUsageIndexer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IterableQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\DependencyInjection\ServiceLocator;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentLayoutMediaUsageIndexer::class)]
class ContentLayoutMediaUsageIndexerTest extends TestCase
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
        $this->connection->method('executeStatement')->willReturnCallback(function (string $sql, array $params = []): int {
            $this->statements[] = [$sql, $params];

            return 1;
        });
    }

    public function testIsNamedAfterItsTable(): void
    {
        static::assertSame('content_layout_media_usage.indexer', $this->createIndexer()->getName());
    }

    public function testIndexesLayoutsWithAChangedTree(): void
    {
        $layoutId = Uuid::randomHex();

        $message = $this->createIndexer()->update($this->createEvent($layoutId, [ContentLayoutDefinition::LAYOUT_FIELD => []]));

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame([$layoutId], $message->getData());
    }

    public function testIgnoresWritesWhichDoNotChangeTheTree(): void
    {
        static::assertNull($this->createIndexer()->update($this->createEvent(Uuid::randomHex(), ['name' => 'layout'])));
    }

    public function testMapsTheStoredMediaOfEveryElementToTheLayout(): void
    {
        $layoutId = Uuid::randomBytes();
        $backgroundId = Uuid::randomHex();
        $galleryIds = [Uuid::randomHex(), Uuid::randomHex()];

        $this->connection->method('fetchAllKeyValue')->willReturn([
            Uuid::fromBytesToHex($layoutId) => json_encode([
                $this->element('container', ['backgroundImageId' => $backgroundId], ['backgroundImage' => $this->requirement('entity', 'media', 'backgroundImageId')], [
                    'content' => [
                        $this->element('gallery', ['mediaIds' => $galleryIds], ['mediaItems' => $this->requirement('entity_collection', 'media', 'mediaIds')]),
                        $this->element('slider', ['productIds' => [Uuid::randomHex()]], ['products' => $this->requirement('entity_collection', 'product', 'productIds')]),
                        $this->element('mapped-image', [], ['media' => $this->requirement('entity', 'media', 'mediaId')]),
                        $this->element('same-background', ['backgroundImageId' => $backgroundId], ['backgroundImage' => $this->requirement('entity', 'media', 'backgroundImageId')]),
                    ],
                ]),
            ]),
        ]);

        $this->createIndexer()->handle(new EntityIndexingMessage([Uuid::fromBytesToHex($layoutId)]));

        [$delete] = $this->statements;
        static::assertSame('DELETE FROM content_layout_media_usage WHERE content_layout_id IN (:ids)', $delete[0]);
        static::assertSame(['ids' => [$layoutId]], $delete[1], 'the previous usages of the layout are removed');
        static::assertEqualsCanonicalizing(
            [
                [$layoutId, Uuid::fromHexToBytes($backgroundId)],
                [$layoutId, Uuid::fromHexToBytes($galleryIds[0])],
                [$layoutId, Uuid::fromHexToBytes($galleryIds[1])],
            ],
            $this->getInsertedRows(),
            'products, mapped media and repeated media add no rows'
        );
    }

    public function testDoesNothingWithoutLayouts(): void
    {
        $this->createIndexer()->handle(new EntityIndexingMessage([]));

        static::assertSame([], $this->statements);
    }

    public function testIteratesTheLayoutsForAFullReindex(): void
    {
        $layoutId = Uuid::randomHex();
        $iterator = static::createStub(IterableQuery::class);
        $iterator->method('fetch')->willReturnOnConsecutiveCalls([Uuid::fromHexToBytes($layoutId) => $layoutId], []);
        $iterator->method('getOffset')->willReturn(['offset' => 1]);
        $iterator->method('fetchCount')->willReturn(1);

        $iteratorFactory = $this->createMock(IteratorFactory::class);
        $iteratorFactory->expects($this->exactly(3))->method('createIterator')->willReturn($iterator);

        $indexer = $this->createIndexer($iteratorFactory);
        $message = $indexer->iterate(null);

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame([$layoutId], $message->getData());
        static::assertNull($indexer->iterate($message->getOffset()), 'the iteration ends without layouts');
        static::assertSame(1, $indexer->getTotal());
    }

    public function testCanNotBeDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->createIndexer()->getDecorated();
    }

    private function createIndexer(?IteratorFactory $iteratorFactory = null): ContentLayoutMediaUsageIndexer
    {
        $serializers = new ServiceLocator([
            'entity' => static fn (): EntityLoaderConfigSerializer => new EntityLoaderConfigSerializer(),
            'entity_collection' => static fn (): EntityCollectionLoaderConfigSerializer => new EntityCollectionLoaderConfigSerializer(new EntityLoaderConfigSerializer()),
        ]);

        return new ContentLayoutMediaUsageIndexer(
            $iteratorFactory ?? static::createStub(IteratorFactory::class),
            $this->connection,
            new StoredTreeCodec(new StoredElementCodec(new DataLoaderConfigSerializerProvider($serializers))),
        );
    }

    /**
     * @param array<string, mixed> $properties
     * @param array<string, array<string, mixed>> $dataRequirements
     * @param array<string, list<array<string, mixed>>> $slots
     *
     * @return array<string, mixed>
     */
    private function element(string $id, array $properties, array $dataRequirements, array $slots = []): array
    {
        foreach ($dataRequirements as $key => $requirement) {
            $dataRequirements[$key] = ['key' => $key, ...$requirement];
        }

        $element = ['id' => $id, 'component' => 'Test:Element', 'properties' => $properties, 'dataRequirements' => $dataRequirements];
        if ($slots !== []) {
            $element['slots'] = $slots;
        }

        return $element;
    }

    /**
     * @return array<string, mixed>
     */
    private function requirement(string $source, string $entity, string $property): array
    {
        return ['source' => $source, 'config' => ['entity' => $entity, 'property' => $property]];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createEvent(string $layoutId, array $payload): EntityWrittenContainerEvent
    {
        return EntityWrittenContainerEvent::createWithWrittenEvents([
            ContentLayoutDefinition::ENTITY_NAME => [
                new EntityWriteResult($layoutId, $payload, ContentLayoutDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_UPDATE),
            ],
        ], Context::createDefaultContext(), []);
    }

    /**
     * @return list<list<string>> the values of the inserted rows
     */
    private function getInsertedRows(): array
    {
        $rows = [];
        foreach ($this->statements as [$sql, $params]) {
            if (!str_starts_with($sql, 'INSERT')) {
                continue;
            }

            foreach (array_chunk(array_values($params), 2) as $row) {
                $rows[] = array_map(strval(...), $row);
            }
        }

        return $rows;
    }
}

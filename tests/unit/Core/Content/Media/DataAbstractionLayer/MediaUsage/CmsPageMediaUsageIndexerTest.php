<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\Cms\CmsElementMediaIdsProvider;
use Shopware\Core\Content\Media\Cms\ImageCmsElementResolver;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\CmsPageMediaUsageIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\CmsSlotMediaIdResolver;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CmsPageMediaUsageIndexer::class)]
class CmsPageMediaUsageIndexerTest extends TestCase
{
    private Connection&Stub $connection;

    /**
     * @var list<array<int|string, mixed>> parameters of the executed statements
     */
    private array $statements = [];

    private CmsPageMediaUsageIndexer $indexer;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('transactional')->willReturnCallback(fn (\Closure $closure) => $closure($this->connection));
        $this->connection->method('executeStatement')->willReturnCallback(function (string $sql, array $params): int {
            $this->statements[] = $params;

            return 1;
        });

        $this->indexer = new CmsPageMediaUsageIndexer(
            static::createStub(IteratorFactory::class),
            $this->connection,
            new CmsSlotMediaIdResolver([new CmsElementMediaIdsProvider(new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class)), ['media'])]),
        );
    }

    public function testIsNamedAfterItsTable(): void
    {
        static::assertSame('cms_page_media_usage.indexer', $this->indexer->getName());
    }

    public function testIndexesWrittenLayouts(): void
    {
        $pageId = Uuid::randomHex();

        $message = $this->indexer->update($this->createEvent(CmsPageDefinition::ENTITY_NAME, $pageId, ['name' => 'layout']));

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame([$pageId], $message->getData());
    }

    public function testIndexesTheLayoutOfSectionsWithChangedBackgrounds(): void
    {
        $pageId = Uuid::randomHex();
        $this->connection->method('fetchFirstColumn')->willReturn([$pageId]);

        $message = $this->indexer->update($this->createEvent(CmsSectionDefinition::ENTITY_NAME, Uuid::randomHex(), ['backgroundMediaId' => Uuid::randomHex()]));

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame([$pageId], $message->getData());
    }

    public function testIgnoresWritesWhichDoNotChangeMedia(): void
    {
        $message = $this->indexer->update($this->createEvent(CmsSectionDefinition::ENTITY_NAME, Uuid::randomHex(), ['position' => 1]));

        static::assertNull($message);
    }

    public function testMapsBackgroundsAndElementMediaToTheLayout(): void
    {
        $pageId = Uuid::randomBytes();
        $versionId = Uuid::randomBytes();
        $backgroundId = Uuid::randomBytes();
        $imageId = Uuid::randomHex();

        $this->connection->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [['cms_page_id' => $pageId, 'cms_page_version_id' => $versionId, 'media_id' => $backgroundId]],
            [],
            [
                ['cms_page_id' => $pageId, 'cms_page_version_id' => $versionId, 'type' => 'image', 'config' => json_encode(['media' => ['source' => 'static', 'value' => $imageId]])],
                ['cms_page_id' => $pageId, 'cms_page_version_id' => $versionId, 'type' => 'text', 'config' => json_encode(['content' => ['source' => 'static', 'value' => Uuid::randomHex()]])],
            ],
        );

        $this->indexer->handle(new EntityIndexingMessage([Uuid::fromBytesToHex($pageId)]));

        static::assertSame([$pageId], $this->getReplacedIds(), 'the previous usages of the layout are removed');
        static::assertEqualsCanonicalizing(
            [[$pageId, $versionId, $backgroundId], [$pageId, $versionId, Uuid::fromHexToBytes($imageId)]],
            $this->getInsertedRows(columns: 3)
        );
    }

    public function testDoesNothingWithoutLayouts(): void
    {
        $this->indexer->handle(new EntityIndexingMessage([]));

        static::assertSame([], $this->statements);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function createEvent(string $entity, string $id, array $payload): EntityWrittenContainerEvent
    {
        $context = Context::createDefaultContext();
        $result = new EntityWriteResult($id, $payload, $entity, EntityWriteResult::OPERATION_UPDATE);

        return new EntityWrittenContainerEvent($context, new NestedEventCollection([new EntityWrittenEvent($entity, [$result], $context)]), []);
    }

    /**
     * @return list<string> binary ids whose usages were removed before inserting the current ones
     */
    private function getReplacedIds(): array
    {
        foreach ($this->statements as $params) {
            if (isset($params['ids']) && \is_array($params['ids'])) {
                return array_values($params['ids']);
            }
        }

        return [];
    }

    /**
     * @param positive-int $columns
     *
     * @return list<list<string>> the values of the inserted rows
     */
    private function getInsertedRows(int $columns): array
    {
        $rows = [];
        foreach ($this->statements as $params) {
            if (isset($params['ids'])) {
                continue;
            }

            foreach (array_chunk(array_values($params), $columns) as $row) {
                $rows[] = array_map(strval(...), $row);
            }
        }

        return $rows;
    }
}

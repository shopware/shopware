<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\DataAbstractionLayer\MediaUsage;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Content\Media\Cms\AbstractDefaultMediaResolver;
use Shopware\Core\Content\Media\Cms\CmsElementMediaIdsProvider;
use Shopware\Core\Content\Media\Cms\ImageCmsElementResolver;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\CmsSlotMediaIdResolver;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaUsage\CmsSlotMediaUsageIndexer;
use Shopware\Core\Defaults;
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
#[CoversClass(CmsSlotMediaUsageIndexer::class)]
class CmsSlotMediaUsageIndexerTest extends TestCase
{
    private Connection&Stub $connection;

    /**
     * @var list<array<int|string, mixed>> parameters of the executed statements
     */
    private array $statements = [];

    private CmsSlotMediaUsageIndexer $indexer;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('transactional')->willReturnCallback(fn (\Closure $closure) => $closure($this->connection));
        $this->connection->method('executeStatement')->willReturnCallback(function (string $sql, array $params): int {
            $this->statements[] = $params;

            return 1;
        });

        $this->indexer = new CmsSlotMediaUsageIndexer(
            static::createStub(IteratorFactory::class),
            $this->connection,
            new CmsSlotMediaIdResolver([new CmsElementMediaIdsProvider(new ImageCmsElementResolver(static::createStub(AbstractDefaultMediaResolver::class)), ['media'])]),
        );
    }

    public function testIsNamedAfterItsTable(): void
    {
        static::assertSame('cms_page_media_usage.indexer', $this->indexer->getName());
    }

    public function testIndexesSlotsWithChangedConfig(): void
    {
        $slotId = Uuid::randomHex();
        $primaryKey = ['cmsSlotId' => $slotId, 'languageId' => Uuid::randomHex()];

        $message = $this->indexer->update($this->createEvent(CmsSlotTranslationDefinition::ENTITY_NAME, $primaryKey, ['config' => []]));

        static::assertInstanceOf(EntityIndexingMessage::class, $message);
        static::assertSame([$slotId], $message->getData());
    }

    public function testIgnoresWritesWhichDoNotChangeMedia(): void
    {
        $primaryKey = ['cmsSlotId' => Uuid::randomHex(), 'languageId' => Uuid::randomHex()];

        $message = $this->indexer->update($this->createEvent(CmsSlotTranslationDefinition::ENTITY_NAME, $primaryKey, ['customFields' => []]));

        static::assertNull($message);
    }

    public function testMapsTheMediaOfElementsToTheirSlotAndLayout(): void
    {
        $pageId = Uuid::randomBytes();
        $versionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);
        $slotId = Uuid::randomBytes();
        $imageId = Uuid::randomHex();

        $this->connection->method('fetchAllAssociative')->willReturn([
            ['cms_page_id' => $pageId, 'cms_page_version_id' => $versionId, 'cms_slot_id' => $slotId, 'cms_slot_version_id' => $versionId, 'type' => 'image', 'config' => json_encode(['media' => ['source' => 'static', 'value' => $imageId]])],
        ]);

        $this->indexer->handle(new EntityIndexingMessage([Uuid::fromBytesToHex($slotId)]));

        static::assertSame([[$versionId, [$slotId]]], $this->getDeletes(), 'the previous usages of the live slot are removed');
        static::assertSame([[$pageId, $versionId, $slotId, $versionId, Uuid::fromHexToBytes($imageId)]], $this->getInsertedRows(columns: 5));
    }

    public function testDoesNothingWithoutSlots(): void
    {
        $this->indexer->handle(new EntityIndexingMessage([]));

        static::assertSame([], $this->statements);
    }

    /**
     * @param array<string, string>|string $primaryKey
     * @param array<string, mixed> $payload
     */
    private function createEvent(string $entity, array|string $primaryKey, array $payload): EntityWrittenContainerEvent
    {
        $context = Context::createDefaultContext();
        $result = new EntityWriteResult($primaryKey, $payload, $entity, EntityWriteResult::OPERATION_UPDATE);

        return new EntityWrittenContainerEvent($context, new NestedEventCollection([new EntityWrittenEvent($entity, [$result], $context)]), []);
    }

    /**
     * @return list<array{string, list<string>}> the binary version and ids of every delete
     */
    private function getDeletes(): array
    {
        $deletes = [];
        foreach ($this->statements as $params) {
            if (isset($params['ids'], $params['version']) && \is_array($params['ids'])) {
                $deletes[] = [(string) $params['version'], array_values(array_map(strval(...), $params['ids']))];
            }
        }

        return $deletes;
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

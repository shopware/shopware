<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Subscriber;

use Doctrine\DBAL\Connection;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use League\Flysystem\UnableToDeleteDirectory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Media\Event\MediaIndexerEvent;
use Shopware\Core\Content\Product\Aggregate\ProductDocument\ProductDocumentDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductDocumentTranslation\ProductDocumentTranslationDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Document\ProductDocumentArchiver;
use Shopware\Core\Content\Product\Subscriber\ProductDocumentArchiveSubscriber;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Cache\InvalidateCacheEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeletedEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDocumentArchiveSubscriber::class)]
class ProductDocumentArchiveSubscriberTest extends TestCase
{
    private Connection&Stub $connection;

    private CacheInvalidator&MockObject $cacheInvalidator;

    private Filesystem $privateFilesystem;

    private string $productId;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $this->privateFilesystem = new Filesystem(new InMemoryFilesystemAdapter());
        $this->productId = Uuid::randomHex();
    }

    public function testDocumentWritesInvalidateTheArchiveTagOfTheirProduct(): void
    {
        $this->connection->method('fetchFirstColumn')->willReturn([$this->productId]);
        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ProductDocumentArchiver::buildTag($this->productId)]);

        $this->createSubscriber()->invalidateWrittenDocuments($this->createWrittenEvent([
            ProductDocumentDefinition::ENTITY_NAME => [['id' => Uuid::randomHex(), 'versionId' => Defaults::LIVE_VERSION]],
            ProductDocumentTranslationDefinition::ENTITY_NAME => [[
                'productDocumentId' => Uuid::randomHex(),
                'productDocumentVersionId' => Defaults::LIVE_VERSION,
                'languageId' => Defaults::LANGUAGE_SYSTEM,
            ]],
        ]));
    }

    public function testWritesOfOtherEntitiesInvalidateNothing(): void
    {
        $this->connection->method('fetchFirstColumn')->willReturn([$this->productId]);
        $this->cacheInvalidator->expects($this->never())->method('invalidate');

        $this->createSubscriber()->invalidateWrittenDocuments($this->createWrittenEvent([
            ProductDefinition::ENTITY_NAME => [Uuid::randomHex()],
        ]));
    }

    public function testDeletedDocumentsInvalidateTheArchiveTagAfterTheDelete(): void
    {
        $event = $this->createMock(EntityDeleteEvent::class);
        $event->method('getIds')->willReturnMap([
            [ProductDocumentDefinition::ENTITY_NAME, [['id' => Uuid::randomHex(), 'versionId' => Defaults::LIVE_VERSION]]],
            [ProductDocumentTranslationDefinition::ENTITY_NAME, []],
        ]);
        $event->expects($this->once())
            ->method('addSuccess')
            ->willReturnCallback(static function (\Closure $callback): void {
                $callback();
            });

        $this->connection->method('fetchFirstColumn')->willReturn([$this->productId]);
        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ProductDocumentArchiver::buildTag($this->productId)]);

        $this->createSubscriber()->invalidateDeletedDocuments($event);
    }

    public function testReplacedFilesInvalidateTheArchiveTagsOfTheirDocuments(): void
    {
        $this->connection->method('fetchFirstColumn')->willReturn([$this->productId]);
        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ProductDocumentArchiver::buildTag($this->productId)]);

        $this->createSubscriber()->invalidateIndexedFiles(new MediaIndexerEvent([Uuid::randomHex()], Context::createDefaultContext(), []));
    }

    public function testDeletedProductsInvalidateTheirArchiveTags(): void
    {
        $this->cacheInvalidator->expects($this->once())
            ->method('invalidate')
            ->with([ProductDocumentArchiver::buildTag($this->productId)]);

        $this->createSubscriber()->invalidateDeletedProducts(new EntityDeletedEvent(
            ProductDefinition::ENTITY_NAME,
            [new EntityWriteResult($this->productId, [], ProductDefinition::ENTITY_NAME, EntityWriteResult::OPERATION_DELETE)],
            Context::createDefaultContext(),
        ));
    }

    public function testPurgedArchiveTagsDeleteTheStoredArchivesOfTheirProduct(): void
    {
        $otherProductId = Uuid::randomHex();
        $archive = ProductDocumentArchiver::DIRECTORY . '/' . $this->productId . '/documents.zip';
        $otherArchive = ProductDocumentArchiver::DIRECTORY . '/' . $otherProductId . '/documents.zip';
        $this->privateFilesystem->write($archive, 'zip');
        $this->privateFilesystem->write($otherArchive, 'zip');
        $this->cacheInvalidator->expects($this->never())->method('invalidate');

        $this->createSubscriber()->deleteArchives(new InvalidateCacheEvent([
            'product-' . $otherProductId,
            ProductDocumentArchiver::buildTag($this->productId),
        ]));

        static::assertFalse($this->privateFilesystem->fileExists($archive));
        static::assertTrue($this->privateFilesystem->fileExists($otherArchive));
    }

    public function testArchivesThatCannotBeDeletedAreLogged(): void
    {
        $filesystem = static::createStub(FilesystemOperator::class);
        $filesystem->method('deleteDirectory')->willThrowException(UnableToDeleteDirectory::atLocation('archives'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning');
        $this->cacheInvalidator->expects($this->never())->method('invalidate');

        $subscriber = new ProductDocumentArchiveSubscriber($this->connection, $this->cacheInvalidator, $filesystem, $logger);
        $subscriber->deleteArchives(new InvalidateCacheEvent([ProductDocumentArchiver::buildTag($this->productId)]));
    }

    private function createSubscriber(): ProductDocumentArchiveSubscriber
    {
        return new ProductDocumentArchiveSubscriber($this->connection, $this->cacheInvalidator, $this->privateFilesystem, new NullLogger());
    }

    /**
     * @param array<string, list<string|array<string, string>>> $primaryKeys
     */
    private function createWrittenEvent(array $primaryKeys): EntityWrittenContainerEvent
    {
        $context = Context::createDefaultContext();
        $events = [];
        foreach ($primaryKeys as $entity => $keys) {
            $events[] = new EntityWrittenEvent(
                $entity,
                array_map(static fn (string|array $key): EntityWriteResult => new EntityWriteResult($key, [], $entity, EntityWriteResult::OPERATION_UPDATE), $keys),
                $context,
            );
        }

        return new EntityWrittenContainerEvent($context, new NestedEventCollection($events), []);
    }
}

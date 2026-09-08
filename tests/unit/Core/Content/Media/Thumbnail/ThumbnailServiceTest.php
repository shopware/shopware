<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Thumbnail;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeEntity;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexingMessage;
use Shopware\Core\Content\Media\Event\ThumbnailGeneratedEvent;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaType\DocumentType;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\Thumbnail\Processor\GdImageThumbnailProcessor;
use Shopware\Core\Content\Media\Thumbnail\Processor\ThumbnailProcessorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailSizeCalculator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexer;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ThumbnailService::class)]
class ThumbnailServiceTest extends TestCase
{
    private ThumbnailService $thumbnailService;

    private Context $context;

    private FilesystemOperator&Stub $filesystemPublic;

    private FilesystemOperator&Stub $filesystemPrivate;

    private EventDispatcherInterface&Stub $dispatcher;

    private EntityIndexer&Stub $indexer;

    private Connection&Stub $connection;

    private ThumbnailSizeCalculator $thumbnailSizeCalculator;

    private LoggerInterface $logger;

    private Filesystem $filesystem;

    /**
     * @var StaticEntityRepository<MediaThumbnailCollection>
     */
    private StaticEntityRepository $thumbnailRepository;

    /**
     * @var StaticEntityRepository<MediaFolderCollection>
     */
    private StaticEntityRepository $mediaFolderRepository;

    protected function setUp(): void
    {
        $this->filesystemPublic = static::createStub(FilesystemOperator::class);
        $this->filesystemPrivate = static::createStub(FilesystemOperator::class);
        $this->dispatcher = static::createStub(EventDispatcherInterface::class);
        $this->indexer = static::createStub(EntityIndexer::class);
        $this->connection = static::createStub(Connection::class);
        $this->thumbnailSizeCalculator = new ThumbnailSizeCalculator();
        $this->logger = new NullLogger();
        $this->filesystem = new Filesystem();
        $this->context = Context::createDefaultContext();
        $this->thumbnailRepository = new StaticEntityRepository([]);
        $this->mediaFolderRepository = new StaticEntityRepository([]);
        $this->thumbnailService = $this->createThumbnailService();
    }

    public function testGenerateWithValidMediaCollection(): void
    {
        $expected = [
            'id' => 'media-thumbnail-id-1',
        ];

        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();
        $mediaFolderEntity = $this->createMediaFolderEntity();

        $file = $this->filesystem->readFile(__DIR__ . '/fixtures/shopware-logo.png');
        $filesystemPublic = $this->createMock(FilesystemOperator::class);
        $filesystemPublic->expects($this->once())->method('read')->willReturn($file);

        $mediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $mediaThumbnailEntity->setMedia($mediaEntity);
        $mediaCollection = new MediaCollection([$mediaEntity]);

        $indexer = $this->createMock(EntityIndexer::class);
        $indexer->expects($this->once())
            ->method('handle')
            ->with(static::isInstanceOf(MediaIndexingMessage::class));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturnCallback(static function ($sql, $params) {
                return [
                    Uuid::fromBytesToHex($params['ids'][0]) => '/shopware-logo.png',
                ];
            });

        $result = $this->createThumbnailService($filesystemPublic, $indexer, $connection)->generate($mediaCollection, $this->context);
        static::assertSame(1, $result);

        static::assertCount(1, $this->thumbnailRepository->deletes);
        $deleted = $this->thumbnailRepository->deletes[0][0];
        static::assertArrayHasKey('id', $deleted);
        static::assertSame($expected, $deleted);

        static::assertCount(1, $this->thumbnailRepository->creates);
        $created = $this->thumbnailRepository->creates[0][0];
        static::assertArrayHasKey('id', $created);
        static::assertSame('media-id-1', $created['mediaId']);
        static::assertSame('media-thumbnail-size-id-1', $created['mediaThumbnailSizeId']);
        static::assertSame(100, $created['width']);
        static::assertSame(100, $created['height']);
    }

    public function testGenerateWithValidMediaCollectionKeepAspectRatio(): void
    {
        $expected = [
            'id' => 'media-thumbnail-id-1',
        ];

        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();
        $mediaFolderEntity = $this->createMediaFolderEntity();
        static::assertNotNull($mediaFolderEntity->getConfiguration(), 'Media folder configuration should not be null');
        $mediaFolderEntity->getConfiguration()->setKeepAspectRatio(true);

        $file = $this->filesystem->readFile(__DIR__ . '/fixtures/shopware-logo.png');
        $filesystemPublic = $this->createMock(FilesystemOperator::class);
        $filesystemPublic->expects($this->once())->method('read')->willReturn($file);

        $mediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $mediaThumbnailEntity->setMedia($mediaEntity);
        $mediaCollection = new MediaCollection([$mediaEntity]);

        $indexer = $this->createMock(EntityIndexer::class);
        $indexer->expects($this->once())
            ->method('handle')
            ->with(static::isInstanceOf(MediaIndexingMessage::class));

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturnCallback(static function ($sql, $params) {
                return [
                    Uuid::fromBytesToHex($params['ids'][0]) => '/shopware-logo.png',
                ];
            });

        $result = $this->createThumbnailService($filesystemPublic, $indexer, $connection)->generate($mediaCollection, $this->context);
        static::assertSame(1, $result);

        static::assertCount(1, $this->thumbnailRepository->deletes);
        $deleted = $this->thumbnailRepository->deletes[0][0];
        static::assertArrayHasKey('id', $deleted);
        static::assertSame($expected, $deleted);

        static::assertCount(1, $this->thumbnailRepository->creates);
        $created = $this->thumbnailRepository->creates[0][0];
        static::assertArrayHasKey('id', $created);
        static::assertSame('media-id-1', $created['mediaId']);
        static::assertSame('media-thumbnail-size-id-1', $created['mediaThumbnailSizeId']);
        static::assertSame(100, $created['width']);
        static::assertSame(53, $created['height']);
    }

    public function testGenerateWithEmptyMediaCollection(): void
    {
        $mediaCollection = new MediaCollection([]);
        $result = $this->thumbnailService->generate($mediaCollection, $this->context);

        static::assertSame(0, $result);
    }

    public function testGenerateWithMediaWithoutThumbnails(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');

        $mediaCollection = new MediaCollection([$mediaEntity]);

        $this->expectExceptionObject(MediaException::thumbnailAssociationNotLoaded());

        $result = $this->thumbnailService->generate($mediaCollection, $this->context);

        static::assertSame(0, $result);
    }

    public function testGenerateWithNonImageMediaTypes(): void
    {
        $this->thumbnailRepository->addSearch([
            'id' => 'media-thumbnail-id-1',
        ]);

        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();

        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');
        $mediaEntity->setMediaType(new DocumentType());
        $mediaEntity->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $mediaCollection = new MediaCollection([$mediaEntity]);

        $result = $this->thumbnailService->generate($mediaCollection, $this->context);

        static::assertSame(0, $result);
    }

    public function testGenerateWithInvalidMediaConfiguration(): void
    {
        $this->thumbnailRepository->addSearch([
            'id' => 'media-thumbnail-id-1',
        ]);

        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();

        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');
        $mediaEntity->setMediaFolder(new MediaFolderEntity());
        $mediaEntity->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $mediaCollection = new MediaCollection([$mediaEntity]);

        $result = $this->thumbnailService->generate($mediaCollection, $this->context);

        static::assertSame(0, $result);
    }

    public function testUpdateWithValidMediaCollection(): void
    {
        $expected = [
            'id' => 'media-thumbnail-id-1',
        ];

        // Use different mediaThumbnailIds, so the ThumbnailService should delete the old thumbnails and generate new ones
        $mediaThumbnailEntity = $this->createMediaThumbnailEntity('abc');
        $mediaFolderEntity = $this->createMediaFolderEntity('def');

        $file = $this->filesystem->readFile(__DIR__ . '/fixtures/shopware-logo.png');
        $filesystemPublic = $this->createMock(FilesystemOperator::class);
        $filesystemPublic->expects($this->once())->method('read')->willReturn($file);

        $mediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $mediaThumbnailEntity->setMedia($mediaEntity);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturnCallback(static function ($sql, $params) {
                return [
                    Uuid::fromBytesToHex($params['ids'][0]) => '/shopware-logo.png',
                ];
            });

        $thumbnailService = $this->createThumbnailService(filesystemPublic: $filesystemPublic, connection: $connection);

        $mediaCollection = new MediaCollection([$mediaEntity]);
        $thumbnailService->generate($mediaCollection, $this->context);

        $newMediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $newMediaEntity->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (\Closure $func) use ($expected, $newMediaEntity, $mediaFolderEntity) {
                $reflection = new \ReflectionFunction($func);
                $staticVars = $reflection->getStaticVariables();

                static::assertCount(1, $staticVars['delete'][0]);
                static::assertSame($newMediaEntity, $staticVars['media']);
                static::assertSame($mediaFolderEntity->getConfiguration(), $staticVars['config']);
                static::assertSame($this->context, $staticVars['context']);
                static::assertInstanceOf(MediaThumbnailSizeCollection::class, $staticVars['toBeCreatedSizes']);
                static::assertCount(1, $staticVars['toBeCreatedSizes']->getElements());

                return $expected;
            });

        $actual = $thumbnailService->updateThumbnails($newMediaEntity, $this->context, false);

        static::assertSame(1, $actual);
    }

    public function testNoUpdateWithValidMediaCollection(): void
    {
        // Use the same mediaThumbnailIds, so the ThumbnailService should not delete the old thumbnails and not generate new ones
        $mediaThumbnailEntity = $this->createMediaThumbnailEntity('abc');
        $mediaFolderEntity = $this->createMediaFolderEntity('abc');

        $file = $this->filesystem->readFile(__DIR__ . '/fixtures/shopware-logo.png');
        $filesystemPublic = $this->createMock(FilesystemOperator::class);
        $filesystemPublic->expects($this->once())->method('read')->willReturn($file);

        $mediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $mediaThumbnailEntity->setMedia($mediaEntity);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllKeyValue')
            ->willReturnCallback(static function ($sql, $params) {
                return [
                    Uuid::fromBytesToHex($params['ids'][0]) => '/shopware-logo.png',
                ];
            });

        $thumbnailService = $this->createThumbnailService(filesystemPublic: $filesystemPublic, connection: $connection);

        $mediaCollection = new MediaCollection([$mediaEntity]);
        $thumbnailService->generate($mediaCollection, $this->context);

        $newMediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $newMediaEntity->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (\Closure $func) use ($newMediaEntity, $mediaFolderEntity) {
                $reflection = new \ReflectionFunction($func);
                $staticVars = $reflection->getStaticVariables();

                static::assertSame([], $staticVars['delete']);
                static::assertSame($newMediaEntity, $staticVars['media']);
                static::assertSame($mediaFolderEntity->getConfiguration(), $staticVars['config']);
                static::assertSame($this->context, $staticVars['context']);
                static::assertInstanceOf(MediaThumbnailSizeCollection::class, $staticVars['toBeCreatedSizes']);
                static::assertCount(0, $staticVars['toBeCreatedSizes']->getElements());

                return [];
            });

        $actual = $thumbnailService->updateThumbnails($newMediaEntity, $this->context, false);

        static::assertSame(0, $actual);
    }

    public function testUpdateThumbnailsWithForceRegeneratesExistingThumbnails(): void
    {
        // Use the same mediaThumbnailSizeId, without force nothing would be regenerated
        $mediaThumbnailEntity = $this->createMediaThumbnailEntity('abc');
        $mediaFolderEntity = $this->createMediaFolderEntity('abc');

        $mediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $mediaThumbnailEntity->setMedia($mediaEntity);

        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('transactional')
            ->willReturnCallback(function (\Closure $func) use ($mediaEntity, $mediaFolderEntity) {
                $reflection = new \ReflectionFunction($func);
                $staticVars = $reflection->getStaticVariables();

                static::assertSame([['id' => 'media-thumbnail-id-1']], $staticVars['delete']);
                static::assertSame($mediaEntity, $staticVars['media']);
                static::assertSame($mediaFolderEntity->getConfiguration(), $staticVars['config']);
                static::assertSame($this->context, $staticVars['context']);
                static::assertInstanceOf(MediaThumbnailSizeCollection::class, $staticVars['toBeCreatedSizes']);
                static::assertCount(1, $staticVars['toBeCreatedSizes']->getElements());

                return [['id' => Uuid::randomHex()]];
            });

        $thumbnailService = $this->createThumbnailService(connection: $connection);

        $actual = $thumbnailService->updateThumbnails($mediaEntity, $this->context, false, true);

        static::assertSame(1, $actual);
    }

    public function testDeleteThumbnailsExecutesRepository(): void
    {
        $expected = [
            'id' => 'media-thumbnail-id-1',
        ];

        $this->thumbnailRepository->addSearch($expected);
        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();

        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');
        $mediaEntity->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $this->thumbnailService->deleteThumbnails($mediaEntity, $this->context);

        static::assertCount(1, $this->thumbnailRepository->deletes);
        static::assertSame($expected, $this->thumbnailRepository->deletes[0][0]);
    }

    public function testDeleteThumbnailThrowsMediaContainsNoThumbnailException(): void
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');

        $this->expectExceptionObject(MediaException::mediaContainsNoThumbnails());

        $this->thumbnailService->deleteThumbnails($mediaEntity, $this->context);
    }

    public function testThumbnailGenerationThrowExceptionWhenRemoteThumbnailEnabled(): void
    {
        $this->expectExceptionObject(MediaException::thumbnailGenerationDisabled());

        $service = new ThumbnailService(
            $this->thumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->mediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->thumbnailSizeCalculator,
            $this->connection,
            new GdImageThumbnailProcessor(),
            $this->logger,
            true,
        );

        $service->generate(new MediaCollection(), $this->context);
    }

    public function testUpdateThumbnailThrowExceptionWhenRemoteThumbnailEnabled(): void
    {
        $this->expectExceptionObject(MediaException::thumbnailGenerationDisabled());

        $service = new ThumbnailService(
            $this->thumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->mediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->thumbnailSizeCalculator,
            $this->connection,
            new GdImageThumbnailProcessor(),
            $this->logger,
            true,
        );

        $service->updateThumbnails(new MediaEntity(), $this->context, false);
    }

    public function testDeleteThumbnailThrowExceptionWhenRemoteThumbnailEnabled(): void
    {
        $this->expectExceptionObject(MediaException::thumbnailGenerationDisabled());

        $service = new ThumbnailService(
            $this->thumbnailRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->mediaFolderRepository,
            $this->dispatcher,
            $this->indexer,
            $this->thumbnailSizeCalculator,
            $this->connection,
            new GdImageThumbnailProcessor(),
            $this->logger,
            true,
        );

        $service->deleteThumbnails(new MediaEntity(), $this->context);
    }

    public function testGenerateSkipsExternalMediaCompletely(): void
    {
        $mediaThumbnailEntity = new MediaThumbnailEntity();
        $mediaThumbnailEntity->setId('external-thumb-id-1');
        $mediaThumbnailEntity->setPath('http://localhost:8000/thumb.jpg');
        $mediaThumbnailEntity->setWidth(200);
        $mediaThumbnailEntity->setHeight(200);
        $mediaThumbnailEntity->setMediaId('media-id-1');

        $media = new MediaEntity();
        $media->setId('media-id-1');
        $media->setPath('http://localhost:8000/image.jpg');
        $media->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $result = $this->thumbnailService->generate(new MediaCollection([$media]), $this->context);

        static::assertSame(0, $result);
        static::assertEmpty($this->thumbnailRepository->deletes);
    }

    public function testUpdateThumbnailsSkipsExternalMedia(): void
    {
        $mediaThumbnailEntity = new MediaThumbnailEntity();
        $mediaThumbnailEntity->setId('external-thumb-id-1');
        $mediaThumbnailEntity->setPath('http://localhost:8000/thumb.jpg');
        $mediaThumbnailEntity->setWidth(200);
        $mediaThumbnailEntity->setHeight(200);
        $mediaThumbnailEntity->setMediaId('media-id-1');

        $media = new MediaEntity();
        $media->setId('media-id-1');
        $media->setPath('http://localhost:8000/image.jpg');
        $media->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $result = $this->thumbnailService->updateThumbnails($media, $this->context, false);

        static::assertSame(0, $result);
        static::assertEmpty($this->thumbnailRepository->deletes);
    }

    public function testGenerateDispatchesThumbnailGeneratedEvent(): void
    {
        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();
        $mediaFolderEntity = $this->createMediaFolderEntity();

        $file = $this->filesystem->readFile(__DIR__ . '/fixtures/shopware-logo.png');
        $filesystemPublic = static::createStub(FilesystemOperator::class);
        $filesystemPublic->method('read')->willReturn($file);
        $filesystemPublic->method('fileSize')->willReturn(100);

        $mediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $mediaThumbnailEntity->setMedia($mediaEntity);
        $mediaCollection = new MediaCollection([$mediaEntity]);

        $dispatchedEvents = [];
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->atLeastOnce())
            ->method('dispatch')
            ->willReturnCallback(function (object $event) use (&$dispatchedEvents) {
                $dispatchedEvents[] = $event::class;

                return $event;
            });

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')
            ->willReturnCallback(static function ($sql, $params) {
                return [
                    Uuid::fromBytesToHex($params['ids'][0]) => '/shopware-logo.png',
                ];
            });

        $service = new ThumbnailService(
            $this->thumbnailRepository,
            $filesystemPublic,
            $this->filesystemPrivate,
            $this->mediaFolderRepository,
            $dispatcher,
            $this->indexer,
            $this->thumbnailSizeCalculator,
            $connection,
            new GdImageThumbnailProcessor(),
            $this->logger,
        );

        $result = $service->generate($mediaCollection, $this->context);

        static::assertSame(1, $result);
        static::assertContains(ThumbnailGeneratedEvent::class, $dispatchedEvents);
    }

    public function testGenerateCleansUpWrittenThumbnailsOnError(): void
    {
        $mediaThumbnailEntity = $this->createMediaThumbnailEntity();
        $mediaFolderEntity = $this->createMediaFolderEntity();

        $file = $this->filesystem->readFile(__DIR__ . '/fixtures/shopware-logo.png');
        $deletedPaths = [];

        $filesystemPublic = static::createStub(FilesystemOperator::class);
        $filesystemPublic->method('read')->willReturn($file);
        $filesystemPublic->method('fileSize')->willReturn(100);
        $filesystemPublic->method('write')->willReturnCallback(static function (): void {});
        $filesystemPublic->method('delete')
            ->willReturnCallback(function (string $path) use (&$deletedPaths): void {
                $deletedPaths[] = $path;
            });

        $mediaEntity = $this->createMediaEntity($mediaThumbnailEntity, $mediaFolderEntity);
        $mediaThumbnailEntity->setMedia($mediaEntity);
        $mediaCollection = new MediaCollection([$mediaEntity]);

        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(static function (object $event) {
                if ($event instanceof ThumbnailGeneratedEvent) {
                    throw new \RuntimeException('Simulated post-processing failure');
                }

                return $event;
            });

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')
            ->willReturnCallback(static function ($sql, $params) {
                return [
                    Uuid::fromBytesToHex($params['ids'][0]) => '/thumbnail/test.png',
                ];
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $service = new ThumbnailService(
            $this->thumbnailRepository,
            $filesystemPublic,
            $this->filesystemPrivate,
            $this->mediaFolderRepository,
            $dispatcher,
            $this->indexer,
            $this->thumbnailSizeCalculator,
            $connection,
            new GdImageThumbnailProcessor(),
            $logger,
        );

        $result = $service->generate($mediaCollection, $this->context);

        static::assertSame(0, $result);
        static::assertContains('/thumbnail/test.png', $deletedPaths);
    }

    public function testGenerateContinuesBatchWhenSingleMediaFails(): void
    {
        $file = $this->filesystem->readFile(__DIR__ . '/fixtures/shopware-logo.png');

        $writtenPaths = [];
        $deletedPaths = [];

        $filesystemPublic = static::createStub(FilesystemOperator::class);
        $filesystemPublic->method('read')->willReturn($file);
        $filesystemPublic->method('fileSize')->willReturn(100);
        $filesystemPublic->method('write')
            ->willReturnCallback(function (string $path) use (&$writtenPaths): void {
                $writtenPaths[] = $path;
            });
        $filesystemPublic->method('delete')
            ->willReturnCallback(function (string $path) use (&$deletedPaths): void {
                $deletedPaths[] = $path;
            });

        $goodMedia = $this->createMediaEntity($this->createMediaThumbnailEntity(), $this->createMediaFolderEntity());
        $goodMedia->setId('media-good');

        $badMedia = $this->createMediaEntity($this->createMediaThumbnailEntity(), $this->createMediaFolderEntity());
        $badMedia->setId('media-bad');

        $mediaCollection = new MediaCollection([$goodMedia, $badMedia]);

        $dispatcher = static::createStub(EventDispatcherInterface::class);
        $dispatcher->method('dispatch')
            ->willReturnCallback(static function (object $event) {
                if ($event instanceof ThumbnailGeneratedEvent && $event->getMediaId() === 'media-bad') {
                    throw new \RuntimeException('Simulated post-processing failure');
                }

                return $event;
            });

        // hand out a distinct thumbnail path per generateAndSave() call (good media first)
        $paths = ['/thumbnail/good.png', '/thumbnail/bad.png'];
        $call = 0;
        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')
            ->willReturnCallback(static function ($sql, $params) use (&$call, $paths) {
                $path = $paths[$call] ?? '/thumbnail/extra.png';
                ++$call;

                return [Uuid::fromBytesToHex($params['ids'][0]) => $path];
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('error')
            ->with(
                static::stringContains('Thumbnail generation failed'),
                static::callback(static fn (array $context): bool => ($context['mediaId'] ?? null) === 'media-bad')
            );

        $service = new ThumbnailService(
            $this->thumbnailRepository,
            $filesystemPublic,
            $this->filesystemPrivate,
            $this->mediaFolderRepository,
            $dispatcher,
            $this->indexer,
            $this->thumbnailSizeCalculator,
            $connection,
            new GdImageThumbnailProcessor(),
            $logger,
        );

        $result = $service->generate($mediaCollection, $this->context);

        static::assertSame(1, $result);
        static::assertContains('/thumbnail/good.png', $writtenPaths);
        static::assertContains('/thumbnail/bad.png', $deletedPaths);
        static::assertNotContains('/thumbnail/good.png', $deletedPaths);
    }

    public function testUpdateThumbnailsDeletesAllThumbnailsForNonImageLocalMedia(): void
    {
        $mediaThumbnailEntity = new MediaThumbnailEntity();
        $mediaThumbnailEntity->setId('thumb-id-1');
        $mediaThumbnailEntity->setPath('/path/to/thumb.pdf');
        $mediaThumbnailEntity->setWidth(100);
        $mediaThumbnailEntity->setHeight(100);
        $mediaThumbnailEntity->setMediaId('media-id-1');

        $media = new MediaEntity();
        $media->setId('media-id-1');
        $media->setPath('/path/to/file.pdf');
        $media->setMediaType(new DocumentType());
        $media->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));

        $result = $this->thumbnailService->updateThumbnails($media, $this->context, false);

        static::assertSame(0, $result);
        static::assertCount(1, $this->thumbnailRepository->deletes);
    }

    #[DataProvider('exifOrientationProvider')]
    public function testGenerateRotatesImageForExifOrientation(int $orientation, float $angle): void
    {
        $fixture = __DIR__ . \sprintf('/fixtures/shopware-logo-orientation-%d.jpg', $orientation);
        $image = new \stdClass();
        $processor = $this->createMock(ThumbnailProcessorInterface::class);
        $processor->expects($this->once())->method('createImageFromString')->willReturn($image);
        $processor->expects($this->once())->method('rotate')->with($image, $angle)->willReturn($image);
        $processor->method('getWidth')->with($image)->willReturn(1530);
        $processor->method('getHeight')->with($image)->willReturn(1021);
        $processor->method('createNewImage')->willReturn($image);
        $processor->method('convertImage')->willReturn('thumbnail');

        $filesystemPublic = static::createStub(FilesystemOperator::class);
        $filesystemPublic->method('read')->willReturn($this->filesystem->readFile($fixture));
        $filesystemPublic->method('fileSize')->willReturn(100);
        $filesystemPublic->method('write')->willReturnCallback(static function (): void {});

        $connection = static::createStub(Connection::class);
        $connection->method('fetchAllKeyValue')->willReturnCallback(static function ($sql, $params): array {
            return [Uuid::fromBytesToHex($params['ids'][0]) => '/thumbnail.jpg'];
        });

        $media = $this->createMediaEntity(
            $this->createMediaThumbnailEntity(),
            $this->createMediaFolderEntity()
        );
        $media->setPath($fixture);
        $media->setFileName('shopware');
        $media->setFileExtension('jpg');
        $media->setMimeType('image/jpeg');

        $service = $this->createThumbnailService($filesystemPublic, connection: $connection, processor: $processor);

        static::assertSame(1, $service->generate(new MediaCollection([$media]), $this->context));
    }

    /**
     * @return \Generator<string, array{orientation: int, angle: float}>
     */
    public static function exifOrientationProvider(): \Generator
    {
        yield 'rotate 180 degrees' => ['orientation' => 3, 'angle' => 180.0];
        yield 'rotate 90 degrees counter-clockwise' => ['orientation' => 6, 'angle' => -90.0];
        yield 'rotate 90 degrees clockwise' => ['orientation' => 8, 'angle' => 90.0];
    }

    private function createThumbnailService(
        ?FilesystemOperator $filesystemPublic = null,
        ?EntityIndexer $indexer = null,
        ?Connection $connection = null,
        ?ThumbnailProcessorInterface $processor = null,
    ): ThumbnailService {
        return new ThumbnailService(
            $this->thumbnailRepository,
            $filesystemPublic ?? $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->mediaFolderRepository,
            $this->dispatcher,
            $indexer ?? $this->indexer,
            $this->thumbnailSizeCalculator,
            $connection ?? $this->connection,
            $processor ?? new GdImageThumbnailProcessor(),
            $this->logger
        );
    }

    private function createMediaEntity(MediaThumbnailEntity $mediaThumbnailEntity, MediaFolderEntity $mediaFolderEntity): MediaEntity
    {
        $mediaEntity = new MediaEntity();
        $mediaEntity->setId('media-id-1');
        $mediaEntity->setThumbnails(new MediaThumbnailCollection([$mediaThumbnailEntity]));
        $mediaEntity->setMediaFolder($mediaFolderEntity);
        $mediaEntity->setFileName('shopware-logo');
        $mediaEntity->setFileExtension('png');
        $mediaEntity->setMimeType('image/png');
        $mediaEntity->setMetaData(['example' => 'metadata']);
        $mediaType = new ImageType();
        $mediaEntity->setMediaType($mediaType);
        $mediaEntity->setFileSize(100);
        $mediaEntity->setPath(__DIR__ . '/fixtures/shopware-logo.png');
        $mediaEntity->setPrivate(false);
        $mediaEntity->setTitle('Test Image');
        $mediaEntity->setMetaDataRaw('{"example": "metadata"}');
        $mediaEntity->setUploadedAt(new \DateTime());
        $mediaEntity->setAlt('Test Alt Text');
        $mediaEntity->setUrl('/url/to/shopware-logo.png');

        return $mediaEntity;
    }

    private function createMediaFolderEntity(string $mediaThumbnailSizeId = 'media-thumbnail-size-id-1'): MediaFolderEntity
    {
        $mediaThumbnailSizeEntity = new MediaThumbnailSizeEntity();
        $mediaThumbnailSizeEntity->setId($mediaThumbnailSizeId);
        $mediaThumbnailSizeEntity->setWidth(100);
        $mediaThumbnailSizeEntity->setHeight(100);

        $mediaFolderConfigEntity = new MediaFolderConfigurationEntity();
        $mediaFolderConfigEntity->setMediaThumbnailSizes(new MediaThumbnailSizeCollection([$mediaThumbnailSizeEntity]));
        $mediaFolderConfigEntity->setCreateThumbnails(true);
        $mediaFolderConfigEntity->setKeepAspectRatio(false);
        $mediaFolderConfigEntity->setThumbnailQuality(80);

        $mediaFolderEntity = new MediaFolderEntity();
        $mediaFolderEntity->setConfiguration($mediaFolderConfigEntity);

        return $mediaFolderEntity;
    }

    private function createMediaThumbnailEntity(string $mediaThumbnailSizeId = 'media-thumbnail-size-id-1'): MediaThumbnailEntity
    {
        $mediaThumbnailEntity = new MediaThumbnailEntity();
        $mediaThumbnailEntity->setId('media-thumbnail-id-1');
        $mediaThumbnailEntity->setWidth(100);
        $mediaThumbnailEntity->setHeight(100);
        $mediaThumbnailEntity->setMediaId('media-id-1');
        $mediaThumbnailEntity->setPath(__DIR__ . '/fixtures/shopware-logo.png');
        $mediaThumbnailEntity->setMediaThumbnailSizeId($mediaThumbnailSizeId);

        return $mediaThumbnailEntity;
    }
}

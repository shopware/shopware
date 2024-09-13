<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Params\MediaLocationStruct;
use Shopware\Core\Content\Media\Core\Params\ThumbnailLocationStruct;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\Infrastructure\Path\SqlMediaLocationBuilder;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\MessageBus\CollectingMessageBus;
use Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\FieldSerializer\MediaDefinition;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
#[Package('buyers-experience')]
#[CoversClass(FileSaver::class)]
class FileSaverTest extends TestCase
{
    /**
     * @var StaticEntityRepository<MediaCollection>
     */
    private StaticEntityRepository $mediaRepository;

    private CollectingMessageBus $messageBus;

    private FileSaver $fileSaver;

    private MockObject&SqlMediaLocationBuilder $locationBuilder;

    private MockObject&AbstractMediaPathStrategy $mediaPathStrategy;

    protected function setUp(): void
    {
        $this->mediaRepository = new StaticEntityRepository([], new MediaDefinition());

        $filesystemPublic = $this->createMock(FilesystemOperator::class);
        $thumbnailService = $this->createMock(ThumbnailService::class);
        $this->messageBus = new CollectingMessageBus();
        $metadataLoader = $this->createMock(MetadataLoader::class);
        $typeDetector = $this->createMock(TypeDetector::class);
        $filesystemPrivate = $this->createMock(FilesystemOperator::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->locationBuilder = $this->createMock(SqlMediaLocationBuilder::class);
        $this->mediaPathStrategy = $this->createMock(AbstractMediaPathStrategy::class);

        $this->fileSaver = new FileSaver(
            $this->mediaRepository,
            $filesystemPublic,
            $filesystemPrivate,
            $thumbnailService,
            $metadataLoader,
            $typeDetector,
            $this->messageBus,
            $eventDispatcher,
            $this->locationBuilder,
            $this->mediaPathStrategy,
            ['png'],
            ['png']
        );
    }

    #[DataProvider('duplicateFileNameProvider')]
    public function testDuplicatedMediaFileNameInFileSystem(bool $isPrivate): void
    {
        $mediaA = new MediaEntity();
        $mediaA->setId(Uuid::randomHex());
        $mediaA->setMimeType('image/png');
        $mediaA->setFileName('foo');
        $mediaA->setFileExtension('png');
        $mediaA->setPrivate(true);

        $mediaB = clone $mediaA;
        $mediaB->setId(Uuid::randomHex());
        $mediaB->setPrivate(false);

        if ($isPrivate) {
            $mediaCollection = new MediaCollection([$mediaA]);
        } else {
            $mediaCollection = new MediaCollection([$mediaB]);
        }

        $mediaId = Uuid::randomHex();
        $currentMedia = new MediaEntity();
        $currentMedia->setId($mediaId);
        $currentMedia->setPrivate($isPrivate);

        $mediaCollection->set($mediaId, $currentMedia);
        $this->mediaRepository->addSearch($mediaCollection, $mediaCollection);

        $mediaFile = new MediaFile('foo', 'image/png', 'png', 0);

        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('A file with the name "foo.png" already exists.');

        $this->fileSaver->persistFileToMedia($mediaFile, 'foo', $mediaId, $context);
    }

    public static function duplicateFileNameProvider(): \Generator
    {
        yield 'new private file exists as private in database / different filesystems' => [
            true,
        ];

        yield 'new public file exists as public in database / different filesystems' => [
            false,
        ];
    }

    #[DataProvider('uniqueFileNameProvider')]
    public function testFileNameUniqueInFileSystem(
        bool $isPrivate
    ): void {
        $mediaA = new MediaEntity();
        $mediaA->setId(Uuid::randomHex());
        $mediaA->setMimeType('image/png');
        $mediaA->setFileName('foo');
        $mediaA->setFileExtension('png');
        $mediaA->setPrivate(true);

        $mediaB = clone $mediaA;
        $mediaB->setId(Uuid::randomHex());
        $mediaB->setPrivate(false);

        if (!$isPrivate) {
            $mediaCollection = new MediaCollection([$mediaA]);
        } else {
            $mediaCollection = new MediaCollection([$mediaB]);
        }

        $mediaId = Uuid::randomHex();
        $currentMedia = new MediaEntity();
        $currentMedia->setId($mediaId);
        $currentMedia->setPrivate($isPrivate);
        $currentMedia->setPath('');
        $mediaCollection->set($mediaId, $currentMedia);
        $this->mediaRepository->addSearch($mediaCollection, $mediaCollection, $mediaCollection);

        $file = tmpfile();
        static::assertIsResource($file);
        $tempMeta = stream_get_meta_data($file);
        $mediaFile = new MediaFile($tempMeta['uri'] ?? '', 'image/png', 'png', 0);

        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));

        $message = new GenerateThumbnailsMessage();
        $message->setMediaIds([$mediaId]);
        $message->setContext($context);

        $this->fileSaver->persistFileToMedia($mediaFile, 'foo', $mediaId, $context);

        static::assertCount(1, $this->mediaRepository->updates);
        $update = $this->mediaRepository->updates[0];

        static::assertCount(1, $update);
        static::assertEquals($mediaId, $update[0]['id']);
        static::assertEquals('foo', $update[0]['fileName']);

        static::assertArrayHasKey(0, $this->messageBus->getMessages());
        static::assertEquals($message, $this->messageBus->getMessages()[0]->getMessage());
    }

    public static function uniqueFileNameProvider(): \Generator
    {
        yield 'new public file exists as private in database' => [
            false,
        ];

        yield 'new private file exists as public in database' => [
            true,
        ];
    }

    public function testFileNameUniqueWithRemoteThumbnailsEnable(): void
    {
        $fileSaver = new FileSaver(
            $this->mediaRepository,
            $this->createMock(FilesystemOperator::class),
            $this->createMock(FilesystemOperator::class),
            $this->createMock(ThumbnailService::class),
            $this->createMock(MetadataLoader::class),
            $this->createMock(TypeDetector::class),
            $this->messageBus,
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SqlMediaLocationBuilder::class),
            $this->createMock(AbstractMediaPathStrategy::class),
            ['png'],
            ['png'],
            true
        );

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setMimeType('image/png');
        $media->setFileName('foo');
        $media->setFileExtension('png');
        $media->setPrivate(true);

        $mediaCollection = new MediaCollection([$media]);

        $mediaId = Uuid::randomHex();
        $currentMedia = new MediaEntity();
        $currentMedia->setId($mediaId);
        $currentMedia->setPrivate(false);
        $currentMedia->setPath('');
        $mediaCollection->set($mediaId, $currentMedia);
        $this->mediaRepository->addSearch($mediaCollection, $mediaCollection, $mediaCollection);

        $file = tmpfile();
        static::assertIsResource($file);
        $tempMeta = stream_get_meta_data($file);
        $mediaFile = new MediaFile($tempMeta['uri'] ?? '', 'image/png', 'png', 0);

        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));

        $message = new GenerateThumbnailsMessage();
        $message->setMediaIds([$mediaId]);
        $message->setContext($context);

        $fileSaver->persistFileToMedia($mediaFile, 'foo', $mediaId, $context);

        static::assertCount(1, $this->mediaRepository->updates);
        $update = $this->mediaRepository->updates[0];

        static::assertCount(1, $update);
        static::assertEquals($mediaId, $update[0]['id']);
        static::assertEquals('foo', $update[0]['fileName']);

        static::assertEmpty($this->messageBus->getMessages());
    }

    public function testRenameMediaWithMissingFile(): void
    {
        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));

        $media = new MediaEntity();
        $media->setId(Uuid::randomHex());
        $media->setPrivate(false);

        $mediaCollection = new MediaCollection([$media]);
        $this->mediaRepository->addSearch($mediaCollection);

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage("Could not find file for media with id \"{$media->getId()}\"");
        $this->fileSaver->renameMedia($media->getId(), 'foo.png', $context);
    }

    public function testRenameMediaWithMissingMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));

        $mediaCollection = new MediaCollection();

        $this->mediaRepository->addSearch($mediaCollection);

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage("Could not find media with id \"{$mediaId}\"");
        $this->fileSaver->renameMedia($mediaId, 'foo.png', $context);
    }

    public function testRenameMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $thumbnailId = Uuid::randomHex();

        $mediaLocation = new MediaLocationStruct(
            Uuid::randomHex(),
            'png',
            'foo',
            new \DateTimeImmutable()
        );

        $this->locationBuilder->method('media')->willReturn([
            $mediaId => $mediaLocation,
        ]);

        $this->locationBuilder->method('thumbnails')->willReturn([
            $thumbnailId => new ThumbnailLocationStruct(
                Uuid::randomHex(),
                100,
                100,
                $mediaLocation
            ),
        ]);

        $this->mediaPathStrategy->method('generate')->willReturn(
            [
                $mediaId => 'foo.png',
            ],
            [
                $thumbnailId => 'foo.png',
            ]
        );

        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setId($thumbnailId);

        $thumbnails = new MediaThumbnailCollection();
        $thumbnails->add($thumbnail);

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setMimeType('image/png');
        $media->setFileName('foo');
        $media->setFileExtension('png');
        $media->setPrivate(false);
        $media->setThumbnails($thumbnails);

        $mediaCollection = new MediaCollection([$media]);
        $this->mediaRepository->addSearch($mediaCollection, new MediaCollection());

        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));

        $this->fileSaver->renameMedia($mediaId, 'foobar', $context);

        static::assertCount(1, $this->mediaRepository->updates);
        $update = $this->mediaRepository->updates[0];

        static::assertCount(1, $update);
        static::assertEquals($mediaId, $update[0]['id']);
        static::assertEquals('foobar', $update[0]['fileName']);
    }

    public function testRenameMediaWithInvalidThumbnail(): void
    {
        $mediaId = Uuid::randomHex();
        $thumbnailId = Uuid::randomHex();

        $this->locationBuilder->method('media')->willReturn([
            $mediaId => new MediaLocationStruct(
                Uuid::randomHex(),
                'png',
                'foo',
                new \DateTimeImmutable()
            ),
        ]);

        $this->mediaPathStrategy->method('generate')->willReturn(
            [
                $mediaId => 'foo.png',
            ],
            [
                $thumbnailId => null,
            ]
        );

        $thumbnail = new MediaThumbnailEntity();
        $thumbnail->setId($thumbnailId);

        $thumbnails = new MediaThumbnailCollection();
        $thumbnails->add($thumbnail);

        $media = new MediaEntity();
        $media->setId($mediaId);
        $media->setMimeType('image/png');
        $media->setFileName('foo');
        $media->setFileExtension('png');
        $media->setPrivate(false);
        $media->setThumbnails($thumbnails);

        $mediaCollection = new MediaCollection([$media]);
        $this->mediaRepository->addSearch($mediaCollection, new MediaCollection([]));

        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage("Could not rename file for media with id: {$mediaId}. Rollback to filename: \"foo\"");

        $this->fileSaver->renameMedia($mediaId, 'foobar', $context);
    }
}

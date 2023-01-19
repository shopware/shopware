<?php declare(strict_types=1);

namespace unit\php\Core\Content\Media\File;

use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Message\GenerateThumbnailsMessage;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\File\FileSaver
 */
class FileSaverTest extends TestCase
{
    /**
     * @var MockObject|EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var MockObject|FilesystemInterface
     */
    private $filesystemPublic;

    /**
     * @var MockObject|UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var MockObject|ThumbnailService
     */
    private $thumbnailService;

    /**
     * @var MockObject|MessageBusInterface
     */
    private $messageBus;

    /**
     * @var MockObject|MetadataLoader
     */
    private $metadataLoader;

    /**
     * @var MockObject|TypeDetector
     */
    private $typeDetector;

    /**
     * @var MockObject|FilesystemInterface
     */
    private $filesystemPrivate;

    /**
     * @var MockObject|EventDispatcherInterface
     */
    private $eventDispatcher;

    private FileSaver $fileSaver;

    public function setUp(): void
    {
        $this->mediaRepository = $this->createMock(EntityRepositoryInterface::class);
        $this->filesystemPublic = $this->createMock(FilesystemInterface::class);
        $this->urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $this->thumbnailService = $this->createMock(ThumbnailService::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->metadataLoader = $this->createMock(MetadataLoader::class);
        $this->typeDetector = $this->createMock(TypeDetector::class);
        $this->filesystemPrivate = $this->createMock(FilesystemInterface::class);
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->fileSaver = new FileSaver(
            $this->mediaRepository,
            $this->filesystemPublic,
            $this->filesystemPrivate,
            $this->urlGenerator,
            $this->thumbnailService,
            $this->metadataLoader,
            $this->typeDetector,
            $this->messageBus,
            $this->eventDispatcher,
            ['png'],
            ['png']
        );
    }

    /**
     * @dataProvider duplicateFileNameProvider
     */
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
            $mediaWithRelatedFileName = new MediaCollection([$mediaA]);
        } else {
            $mediaWithRelatedFileName = new MediaCollection([$mediaB]);
        }

        $currentMedia = new MediaEntity();
        $currentMedia->setId(Uuid::randomHex());
        $currentMedia->setPrivate($isPrivate);

        $mediaSearchResult = $this->createMock(EntitySearchResult::class);
        $mediaSearchResult->method('get')->willReturn($currentMedia);
        $mediaSearchResult->method('getEntities')->willReturn($mediaWithRelatedFileName);
        $this->mediaRepository->method('search')->willReturn($mediaSearchResult);

        $mediaFile = new MediaFile('foo', 'image/png', 'png', 0);

        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));
        static::expectException(DuplicatedMediaFileNameException::class);
        $this->fileSaver->persistFileToMedia($mediaFile, 'foo', Uuid::randomHex(), $context);
    }

    public function duplicateFileNameProvider(): \Generator
    {
        yield 'new private file exists as private in database / different filesystems' => [
            true,
        ];

        yield 'new public file exists as public in database / different filesystems' => [
            false,
        ];
    }

    /**
     * @dataProvider uniqueFileNameProvider
     */
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
            $mediaWithRelatedFileName = new MediaCollection([$mediaA]);
        } else {
            $mediaWithRelatedFileName = new MediaCollection([$mediaB]);
        }

        $currentMedia = new MediaEntity();
        $currentMedia->setId(Uuid::randomHex());
        $currentMedia->setPrivate($isPrivate);

        $mediaSearchResult = $this->createMock(EntitySearchResult::class);
        $mediaSearchResult->method('get')->willReturn($currentMedia);
        $mediaSearchResult->method('getEntities')->willReturn($mediaWithRelatedFileName);
        $this->mediaRepository->method('search')->willReturn($mediaSearchResult);

        $file = tmpfile();
        static::assertIsResource($file);
        $tempMeta = stream_get_meta_data($file);
        $mediaFile = new MediaFile($tempMeta['uri'], 'image/png', 'png', 0);

        $context = Context::createDefaultContext(new AdminApiSource(Uuid::randomHex()));
        $mediaId = Uuid::randomHex();

        $message = new GenerateThumbnailsMessage();
        $message->setMediaIds([$mediaId]);
        $message->withContext($context);

        $this->messageBus
            ->expects(static::once())
            ->method('dispatch')
            ->with($message);

        $this->mediaRepository
            ->expects(static::once())
            ->method('update')
            ->with(static::callback(static function (array $payload) use ($currentMedia) {
                return $payload[0]['id'] === $currentMedia->getId() && $payload[0]['fileName'] === 'foo';
            }));

        $this->fileSaver->persistFileToMedia($mediaFile, 'foo', $mediaId, $context);
    }

    public function uniqueFileNameProvider(): \Generator
    {
        yield 'new public file exists as private in database' => [
            false,
        ];

        yield 'new private file exists as public in database' => [
            true,
        ];
    }
}

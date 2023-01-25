<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\File;

use League\Flysystem\FilesystemOperator;
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
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\CollectingMessageBus;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Media\File\FileSaver
 */
class FileSaverTest extends TestCase
{
    private MockObject&EntityRepository $mediaRepository;

    private CollectingMessageBus $messageBus;

    private FileSaver $fileSaver;

    public function setUp(): void
    {
        $this->mediaRepository = $this->createMock(EntityRepository::class);
        $filesystemPublic = $this->createMock(FilesystemOperator::class);
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $thumbnailService = $this->createMock(ThumbnailService::class);
        $this->messageBus = new CollectingMessageBus();
        $metadataLoader = $this->createMock(MetadataLoader::class);
        $typeDetector = $this->createMock(TypeDetector::class);
        $filesystemPrivate = $this->createMock(FilesystemOperator::class);
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->fileSaver = new FileSaver(
            $this->mediaRepository,
            $filesystemPublic,
            $filesystemPrivate,
            $urlGenerator,
            $thumbnailService,
            $metadataLoader,
            $typeDetector,
            $this->messageBus,
            $eventDispatcher,
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

        $this->mediaRepository
            ->expects(static::once())
            ->method('update')
            ->with(static::callback(static fn (array $payload) => $payload[0]['id'] === $currentMedia->getId() && $payload[0]['fileName'] === 'foo'));

        $this->fileSaver->persistFileToMedia($mediaFile, 'foo', $mediaId, $context);

        static::assertArrayHasKey(0, $this->messageBus->getMessages());
        static::assertEquals($message, $this->messageBus->getMessages()[0]->getMessage());
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

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Core\Application\AbstractMediaPathStrategy;
use Shopware\Core\Content\Media\Core\Application\MediaLocationBuilder;
use Shopware\Core\Content\Media\Core\Event\UpdateMediaPathEvent;
use Shopware\Core\Content\Media\Core\Event\UpdateThumbnailPathEvent;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexingMessage;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
class FileSaverTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;

    final public const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';
    final public const TEST_SCRIPT_FILE = __DIR__ . '/../fixtures/test.php';

    private EntityRepository $mediaRepository;

    private FileSaver $fileSaver;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->fileSaver = $this->getContainer()->get(FileSaver::class);
    }

    public function testPersistFileToMediaHappyPathForInitialUpload(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        $mediaId = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                ],
            ],
            $context
        );

        try {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                'test-file',
                $mediaId,
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $context)->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $path = $media->getPath();
        static::assertTrue($this->getPublicFilesystem()->has($path));
    }

    public function testPersistFileWithUpperCaseExtension(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'PNG', $fileSize);

        $mediaId = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                ],
            ],
            $context
        );

        try {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                'test-file',
                $mediaId,
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $context)->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $path = $media->getPath();

        static::assertTrue($this->getPublicFilesystem()->has($path));
    }

    public function testPersistFileToMediaRemovesOldFile(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        $context = Context::createDefaultContext();

        $this->setFixtureContext($context);
        $media = $this->getTxt();

        $oldMediaFilePath = $media->getPath();
        $this->getPublicFilesystem()->write($oldMediaFilePath, 'Some ');

        static::assertIsString($media->getFileName());

        try {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                $media->getFileName(),
                $media->getId(),
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
        $media = $this->mediaRepository->search(new Criteria([$media->getId()]), $context)->get($media->getId());
        static::assertInstanceOf(MediaEntity::class, $media);

        $path = $media->getPath();

        static::assertNotEquals($oldMediaFilePath, $path);
        static::assertTrue($this->getPublicFilesystem()->has($path));
    }

    public function testPersistFileToMediaForMediaTypeWithoutThumbs(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(__DIR__ . '/../fixtures/reader.doc', $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'application/doc', 'doc', $fileSize);

        $mediaId = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                ],
            ],
            $context
        );

        try {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                'test-file',
                $mediaId,
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $context)->get($mediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $path = $media->getPath();
        static::assertTrue($this->getPublicFilesystem()->has($path));
    }

    public function testPersistFileToMediaDoesNotAddSuffixOnReplacement(): void
    {
        $context = Context::createDefaultContext();

        $this->setFixtureContext($context);
        $png = $this->getPng();

        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        $pathName = $png->getPath();

        $resource = fopen($tempFile, 'r');
        static::assertIsResource($resource);
        $this->getPublicFilesystem()->writeStream($pathName, $resource);

        static::assertIsString($png->getFileName());
        static::assertNotEmpty($png->getFileName());

        try {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                $png->getFileName(),
                $png->getId(),
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $updatedMedia = $this->mediaRepository->search(new Criteria([$png->getId()]), $context)->get($png->getId());
        static::assertInstanceOf(MediaEntity::class, $updatedMedia);
        static::assertIsString($updatedMedia->getFileName());
        static::assertStringEndsWith($png->getFileName(), $updatedMedia->getFileName());
    }

    public function testPersistFileToMediaThrowsExceptionOnDuplicateFileName(): void
    {
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('A file with the name "pngFileWithExtension.png" already exists.');

        $context = Context::createDefaultContext();

        $this->setFixtureContext($context);
        $png = $this->getPng();

        $newMediaId = Uuid::randomHex();
        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        try {
            $this->mediaRepository->create(
                [
                    [
                        'id' => $newMediaId,
                    ],
                ],
                $context
            );

            static::assertIsString($png->getFileName());
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                $png->getFileName(),
                $newMediaId,
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testPersistFileToMediaAcceptsSameNameWithDifferentExtension(): void
    {
        $context = Context::createDefaultContext();

        $this->setFixtureContext($context);
        $jpg = $this->getJpg();

        $newMediaId = Uuid::randomHex();
        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        try {
            $this->mediaRepository->create(
                [
                    [
                        'id' => $newMediaId,
                    ],
                ],
                $context
            );

            static::assertIsString($jpg->getFileName());
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                $jpg->getFileName(),
                $newMediaId,
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $media = $this->mediaRepository->search(new Criteria([$newMediaId]), $context)->get($newMediaId);
        static::assertInstanceOf(MediaEntity::class, $media);

        $path = $media->getPath();
        static::assertTrue($this->getPublicFilesystem()->has($path));
    }

    public function testPersistFileToMediaThrowsExceptionWithMoreThan255Characters(): void
    {
        $name = str_repeat('a', 256);
        $context = Context::createDefaultContext();

        $this->setFixtureContext($context);
        $png = $this->getPng();

        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        $path = $png->getPath();
        $this->getPublicFilesystem()->write($path, 'some content');

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('The provided file name is too long, the maximum length is 255 characters.');

        $this->fileSaver->persistFileToMedia(
            $mediaFile,
            $name,
            $png->getId(),
            $context
        );
    }

    public function testRenameMediaThrowsExceptionIfMediaDoesNotExist(): void
    {
        $id = Uuid::randomHex();
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage(MediaException::mediaNotFound($id)->getMessage());

        $context = Context::createDefaultContext();
        $this->fileSaver->renameMedia($id, 'new file destination', $context);
    }

    public function testRenameMediaThrowsExceptionIfMediaHasNoFileAttached(): void
    {
        $id = Uuid::randomHex();

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage(MediaException::missingFile($id)->getMessage());

        $context = Context::createDefaultContext();

        $this->mediaRepository->create([
            [
                'id' => $id,
            ],
        ], $context);

        $this->fileSaver->renameMedia($id, 'new destination', $context);
    }

    public function testRenameMediaThrowsExceptionIfFileNameAlreadyExists(): void
    {
        $context = Context::createDefaultContext();

        $ids = new IdsCollection();

        $data = [
            [
                'id' => $ids->get('png'),
                'fileName' => 'original_media',
                'fileExtension' => 'png',
                'path' => 'media/original_media.png',
            ],
            [
                'id' => $ids->get('old'),
                'fileName' => 'another_media',
                'fileExtension' => 'png',
                'path' => 'media/another_media.png',
            ],
        ];

        $this->mediaRepository->create($data, $context);

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage('A file with the name "original_media.png" already exists.');

        $this->fileSaver->renameMedia($ids->get('old'), 'original_media', $context);
    }

    public function testRenameMediaForNewExtensionWorksWithSameName(): void
    {
        $context = Context::createDefaultContext();

        $ids = new IdsCollection();

        $data = [
            [
                'id' => $ids->get('png'),
                'fileName' => 'renamePNG',
                'fileExtension' => 'png',
                'mimeType' => 'image/png',
                'fileSize' => 1024,
            ],
            [
                'id' => $ids->get('txt'),
                'fileName' => 'txtFile',
                'fileExtension' => 'txt',
                'mimeType' => 'txt',
                'fileSize' => 1024,
            ],
        ];

        $this->mediaRepository->create($data, $context);

        $png = $this->mediaRepository
            ->search(new Criteria([$ids->get('png')]), $context)
            ->get($ids->get('png'));

        static::assertInstanceOf(MediaEntity::class, $png);
        $this->getPublicFilesystem()->write($png->getPath(), 'test file content');

        $this->fileSaver->renameMedia($ids->get('png'), 'txtFile', $context);

        $updatedMedia = $this->mediaRepository
            ->search(new Criteria([$ids->get('png')]), $context)
            ->get($ids->get('png'));

        static::assertInstanceOf(MediaEntity::class, $updatedMedia);

        static::assertTrue($this->getPublicFilesystem()->has($updatedMedia->getPath()));
        static::assertFalse($this->getPublicFilesystem()->has('media/rename_png.png'));
    }

    public function testRenameMediaDoesSkipIfOldFileNameEqualsNewOne(): void
    {
        $context = Context::createDefaultContext();

        $ids = new IdsCollection();

        $data = [
            'id' => $ids->get('png'),
            'fileName' => 'skip_with_same_name',
            'fileExtension' => 'png',
            'path' => 'media/skip_with_same_name.png',
        ];

        $this->mediaRepository->create([$data], $context);

        $this->getPublicFilesystem()->write('media/skip_with_same_name.png', 'test file content');

        $this->fileSaver->renameMedia($ids->get('png'), 'skip_with_same_name', $context);

        static::assertTrue($this->getPublicFilesystem()->has('media/skip_with_same_name.png'));
    }

    public function testRenameMediaRenamesOldFileAndThumbnails(): void
    {
        $context = Context::createDefaultContext();

        $data = [
            'id' => $id = Uuid::randomHex(),
            'fileName' => 'testRenameMediaRenamesOldFileAndThumbnails',
            'fileExtension' => 'png',
            'path' => 'media/test.png',
        ];

        $this->mediaRepository->create([$data], $context);

        $png = $this->mediaRepository->search(new Criteria([$id]), $context)->get($id);

        static::assertInstanceOf(MediaEntity::class, $png);

        $thumbnailId = Uuid::randomHex();
        $this->mediaRepository->update([[
            'id' => $png->getId(),
            'thumbnails' => [
                [
                    'id' => $thumbnailId,
                    'width' => 100,
                    'height' => 100,
                    'highDpi' => false,
                ],
            ],
        ]], $context);

        $this->getContainer()->get('event_dispatcher')
            ->dispatch(new UpdateMediaPathEvent([$png->getId()]));

        $this->getContainer()->get('event_dispatcher')
            ->dispatch(new UpdateThumbnailPathEvent([$thumbnailId]));

        $this->getContainer()->get(MediaIndexer::class)->handle(
            new MediaIndexingMessage([$png->getId()], $context)
        );

        /** @var MediaEntity $png */
        $png = $this->mediaRepository->search(new Criteria([$png->getId()]), $context)->get($png->getId());

        static::assertNotNull($png->getThumbnails());
        static::assertGreaterThan(0, $png->getThumbnails()->count());

        $oldMediaPath = $png->getPath();

        static::assertNotNull($png->getThumbnails()->first());
        $oldThumbnailPath = $png->getThumbnails()->first()->getPath();

        $this->getPublicFilesystem()->write($oldMediaPath, 'test file content');
        $this->getPublicFilesystem()->write($oldThumbnailPath, 'test file content');

        $this->fileSaver->renameMedia($png->getId(), 'new destination', $context);

        $updatedMedia = $this->mediaRepository->search(new Criteria([$png->getId()]), $context)->get($png->getId());
        static::assertInstanceOf(MediaEntity::class, $updatedMedia);
        static::assertFalse($this->getPublicFilesystem()->has($oldMediaPath));
        static::assertTrue($this->getPublicFilesystem()->has($updatedMedia->getPath()));

        static::assertFalse($this->getPublicFilesystem()->has($oldThumbnailPath));

        static::assertNotNull($updatedMedia->getThumbnails());
        static::assertGreaterThan(0, $updatedMedia->getThumbnails()->count());

        static::assertNotNull($updatedMedia->getThumbnails()->first());
        $location = $updatedMedia->getThumbnails()->first()->getPath();

        static::assertTrue($this->getPublicFilesystem()->has($location));
    }

    public function testRenameMediaMakesRollbackOnFailure(): void
    {
        $png = $this->getPng();

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage(MediaException::couldNotRenameFile($png->getId(), (string) $png->getFileName())->getMessage());

        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);

        $collection = new MediaCollection([$png]);
        $searchResult = new EntitySearchResult('temp', 1, $collection, null, new Criteria(), $context);

        $repositoryMock = $this->createMock(EntityRepository::class);
        $repositoryMock->expects(static::exactly(2))
            ->method('search')
            ->willReturn($searchResult);

        $repositoryMock->expects(static::once())
            ->method('update')
            ->willThrowException(new \Exception());

        /** @var list<string> $allowed */
        $allowed = $this->getContainer()->getParameter('shopware.filesystem.allowed_extensions');
        /** @var list<string> $allowedPrivate */
        $allowedPrivate = $this->getContainer()->getParameter('shopware.filesystem.private_allowed_extensions');

        $fileSaverWithFailingRepository = new FileSaver(
            $repositoryMock,
            $this->getContainer()->get('shopware.filesystem.public'),
            $this->getContainer()->get('shopware.filesystem.private'),
            $this->getContainer()->get(ThumbnailService::class),
            $this->getContainer()->get(MetadataLoader::class),
            $this->getContainer()->get(TypeDetector::class),
            $this->getContainer()->get('messenger.bus.shopware'),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(MediaLocationBuilder::class),
            $this->getContainer()->get(AbstractMediaPathStrategy::class),
            $allowed,
            $allowedPrivate
        );

        $mediaPath = $png->getPath();
        $this->getPublicFilesystem()->write($mediaPath, 'test file');

        $fileSaverWithFailingRepository->renameMedia($png->getId(), 'new file name', $context);
        $updatedMedia = $this->mediaRepository->search(new Criteria([$png->getId()]), $context)->get($png->getId());

        static::assertInstanceOf(MediaEntity::class, $updatedMedia);
        static::assertSame($png->getFileName(), $updatedMedia->getFileName());
        static::assertTrue($this->getPublicFilesystem()->has($mediaPath));
    }

    public function testMaliciousFileExtension(): void
    {
        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(self::TEST_SCRIPT_FILE, $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'text/plain', 'php', $fileSize);

        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->expectException(MediaException::class);
        $this->expectExceptionMessage(MediaException::fileExtensionNotSupported($mediaId, 'php')->getMessage());

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                ],
            ],
            $context
        );

        try {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                'test-file',
                $mediaId,
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }
    }

    public function testWhitelistEvent(): void
    {
        /** @var EventDispatcher $dispatcher */
        $dispatcher = $this->getContainer()->get('event_dispatcher');

        $eventDidRun = false;
        $listenerClosure = function () use (&$eventDidRun): void {
            $eventDidRun = true;
        };

        $this->addEventListener($dispatcher, MediaFileExtensionWhitelistEvent::class, $listenerClosure);

        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        $mediaId = Uuid::randomHex();

        $context = Context::createDefaultContext();

        $this->mediaRepository->create(
            [
                [
                    'id' => $mediaId,
                ],
            ],
            $context
        );

        try {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                'test-file',
                $mediaId,
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $dispatcher->removeListener(MediaFileExtensionWhitelistEvent::class, $listenerClosure);

        static::assertTrue($eventDidRun, 'The media_whitelist.before_filter event did not run');
    }
}

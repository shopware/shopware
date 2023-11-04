<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\File;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailEntity;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\Exception\CouldNotRenameFileException;
use Shopware\Core\Content\Media\Exception\DuplicatedMediaFileNameException;
use Shopware\Core\Content\Media\Exception\FileExtensionNotSupportedException;
use Shopware\Core\Content\Media\Exception\MediaNotFoundException;
use Shopware\Core\Content\Media\Exception\MissingFileException;
use Shopware\Core\Content\Media\File\FileSaver;
use Shopware\Core\Content\Media\File\MediaFile;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\Metadata\MetadataLoader;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Media\Thumbnail\ThumbnailService;
use Shopware\Core\Content\Media\TypeDetector\TypeDetector;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
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

    private UrlGeneratorInterface $urlGenerator;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);
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
        $path = $this->urlGenerator->getRelativeMediaUrl($media);
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
        $path = $this->urlGenerator->getRelativeMediaUrl($media);
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

        $oldMediaFilePath = $this->urlGenerator->getRelativeMediaUrl($media);
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

        $path = $this->urlGenerator->getRelativeMediaUrl($media);
        static::assertNotEquals($oldMediaFilePath, $path);
        static::assertTrue($this->getPublicFilesystem()->has($path));
        static::assertFalse($this->getPublicFilesystem()->has($oldMediaFilePath));
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
        $path = $this->urlGenerator->getRelativeMediaUrl($media);
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

        $pathName = $this->urlGenerator->getRelativeMediaUrl($png);

        $resource = fopen($tempFile, 'rb');
        static::assertIsResource($resource);
        $this->getPublicFilesystem()->writeStream($pathName, $resource);

        static::assertIsString($png->getFileName());

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
        $this->expectException(DuplicatedMediaFileNameException::class);

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
        $path = $this->urlGenerator->getRelativeMediaUrl($media);
        static::assertTrue($this->getPublicFilesystem()->has($path));
    }

    public function testPersistFileToMediaWorksWithMoreThan255Characters(): void
    {
        $longFileName = '';
        while (mb_strlen($longFileName) < 512) {
            $longFileName .= 'Word';
        }

        $context = Context::createDefaultContext();

        $this->setFixtureContext($context);
        $png = $this->getPng();

        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(self::TEST_IMAGE, $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'image/png', 'png', $fileSize);

        $this->getPublicFilesystem()->write($this->urlGenerator->getRelativeMediaUrl($png), 'some content');

        try {
            $this->fileSaver->persistFileToMedia(
                $mediaFile,
                $longFileName,
                $png->getId(),
                $context
            );
        } finally {
            if (file_exists($tempFile)) {
                unlink($tempFile);
            }
        }

        $updated = $this->mediaRepository->search(new Criteria([$png->getId()]), $context)->get($png->getId());
        static::assertInstanceOf(MediaEntity::class, $updated);
        static::assertIsString($updated->getFileName());
        static::assertStringEndsWith($longFileName, $updated->getFileName());
        static::assertTrue($this->getPublicFilesystem()->has($this->urlGenerator->getRelativeMediaUrl($updated)));
    }

    public function testRenameMediaThrowsExceptionIfMediaDoesNotExist(): void
    {
        $this->expectException(MediaNotFoundException::class);

        $context = Context::createDefaultContext();
        $this->fileSaver->renameMedia(Uuid::randomHex(), 'new file destination', $context);
    }

    public function testRenameMediaThrowsExceptionIfMediaHasNoFileAttached(): void
    {
        $this->expectException(MissingFileException::class);

        $context = Context::createDefaultContext();
        $id = Uuid::randomHex();

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
        $this->setFixtureContext($context);

        $png = $this->getPng();
        $old = $this->getPngWithFolder();

        static::assertIsString($png->getFileName());

        $this->expectException(DuplicatedMediaFileNameException::class);
        $this->fileSaver->renameMedia($old->getId(), $png->getFileName(), $context);
    }

    public function testRenameMediaForNewExtensionWorksWithSameName(): void
    {
        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);

        $png = $this->getPng();
        $txt = $this->getTxt();
        $mediaPath = $this->urlGenerator->getRelativeMediaUrl($png);
        $this->getPublicFilesystem()->write($mediaPath, 'test file content');

        static::assertIsString($txt->getFileName());
        $this->fileSaver->renameMedia($png->getId(), $txt->getFileName(), $context);
        $updatedMedia = $this->mediaRepository->search(new Criteria([$png->getId()]), $context)->get($png->getId());

        static::assertInstanceOf(MediaEntity::class, $updatedMedia);
        $newPath = $this->urlGenerator->getRelativeMediaUrl($updatedMedia);
        static::assertTrue($this->getPublicFilesystem()->has($newPath));
        static::assertFalse($this->getPublicFilesystem()->has($mediaPath));
    }

    public function testRenameMediaDoesSkipIfOldFileNameEqualsNewOne(): void
    {
        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);

        $png = $this->getPng();
        $mediaPath = $this->urlGenerator->getRelativeMediaUrl($png);
        $this->getPublicFilesystem()->write($mediaPath, 'test file content');

        static::assertIsString($png->getFileName());
        $this->fileSaver->renameMedia($png->getId(), $png->getFileName(), $context);
        static::assertTrue($this->getPublicFilesystem()->has($mediaPath));
    }

    public function testRenameMediaRenamesOldFileAndThumbnails(): void
    {
        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);

        $png = $this->getPng();
        $this->mediaRepository->update([[
            'id' => $png->getId(),
            'thumbnails' => [
                [
                    'width' => 100,
                    'height' => 100,
                    'highDpi' => false,
                ],
            ],
        ]], $context);
        $oldMediaPath = $this->urlGenerator->getRelativeMediaUrl($png);
        $oldThumbnailPath = $this->urlGenerator->getRelativeThumbnailUrl($png, (new MediaThumbnailEntity())->assign(['width' => 100, 'height' => 100]));

        $this->getPublicFilesystem()->write($oldMediaPath, 'test file content');
        $this->getPublicFilesystem()->write($oldThumbnailPath, 'test file content');

        $this->fileSaver->renameMedia($png->getId(), 'new destination', $context);
        $updatedMedia = $this->mediaRepository->search(new Criteria([$png->getId()]), $context)->get($png->getId());
        static::assertInstanceOf(MediaEntity::class, $updatedMedia);
        static::assertFalse($this->getPublicFilesystem()->has($oldMediaPath));
        static::assertTrue($this->getPublicFilesystem()->has($this->urlGenerator->getRelativeMediaUrl($updatedMedia)));

        static::assertFalse($this->getPublicFilesystem()->has($oldThumbnailPath));
        static::assertTrue($this->getPublicFilesystem()->has($this->urlGenerator->getRelativeThumbnailUrl($updatedMedia, (new MediaThumbnailEntity())->assign(['width' => 100, 'height' => 100]))));
    }

    public function testRenameMediaMakesRollbackOnFailure(): void
    {
        $this->expectException(CouldNotRenameFileException::class);

        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);
        $png = $this->getPng();

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
            $this->getContainer()->get(UrlGeneratorInterface::class),
            $this->getContainer()->get(ThumbnailService::class),
            $this->getContainer()->get(MetadataLoader::class),
            $this->getContainer()->get(TypeDetector::class),
            $this->getContainer()->get('messenger.bus.shopware'),
            $this->getContainer()->get('event_dispatcher'),
            $allowed,
            $allowedPrivate
        );

        $mediaPath = $this->urlGenerator->getRelativeMediaUrl($png);
        $this->getPublicFilesystem()->write($mediaPath, 'test file');

        $fileSaverWithFailingRepository->renameMedia($png->getId(), 'new file name', $context);
        $updatedMedia = $this->mediaRepository->search(new Criteria([$png->getId()]), $context)->get($png->getId());

        static::assertInstanceOf(MediaEntity::class, $updatedMedia);
        static::assertEquals($png->getFileName(), $updatedMedia->getFileName());
        static::assertTrue($this->getPublicFilesystem()->has($mediaPath));
    }

    public function testMaliciousFileExtension(): void
    {
        $this->expectException(FileExtensionNotSupportedException::class);

        $tempFile = tempnam(sys_get_temp_dir(), '');
        static::assertIsString($tempFile);
        copy(self::TEST_SCRIPT_FILE, $tempFile);

        $fileSize = filesize($tempFile);
        static::assertIsInt($fileSize);
        $mediaFile = new MediaFile($tempFile, 'text/plain', 'php', $fileSize);

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

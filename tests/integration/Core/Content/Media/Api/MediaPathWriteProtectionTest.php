<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Api;

use Doctrine\DBAL\Connection;
use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Mail\Service\Mail;
use Shopware\Core\Content\Mail\Service\MailService;
use Shopware\Core\Content\Mail\Transport\MailerTransportLoader;
use Shopware\Core\Content\MailTemplate\Service\Event\MailBeforeSentEvent;
use Shopware\Core\Content\Media\File\DownloadResponseGenerator;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Integration\EventDispatcher\EventHookDispatcher;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class MediaPathWriteProtectionTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use QueueTestBehaviour;

    private const ILLEGAL_PATH_CODE = 'CONTENT__MEDIA_ILLEGAL_PATH';

    private const ILLEGAL_FILE_NAME_CODE = 'CONTENT__MEDIA_ILLEGAL_FILE_NAME';

    private const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    private FilesystemOperator $privateFilesystem;

    private string $victimPath;

    private string $traversalUrlToVictim;

    protected function setUp(): void
    {
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->privateFilesystem = static::getContainer()->get('shopware.filesystem.private');
        $this->victimPath = 'document/' . Uuid::randomHex() . '-invoice.pdf';
        $this->traversalUrlToVictim = 'https://cdn.example.com/../../' . $this->victimPath;
    }

    protected function tearDown(): void
    {
        if ($this->privateFilesystem->fileExists($this->victimPath)) {
            $this->privateFilesystem->delete($this->victimPath);
        }
    }

    public function testCreatingMediaWithRelativePathIsRejected(): void
    {
        $mediaId = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', '/api/media', [
            'id' => $mediaId,
            'private' => true,
            'path' => $this->victimPath,
        ]);

        $this->assertIllegalPathResponse();
        static::assertNull($this->findMedia($mediaId));
    }

    public function testCreatingMediaWithoutPathIsAllowed(): void
    {
        $mediaId = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', '/api/media', ['id' => $mediaId]);

        $this->assertNoContentResponse();
        static::assertNotNull($this->findMedia($mediaId));
    }

    public function testCreatingMediaWithExplicitNullPathIsAllowed(): void
    {
        $mediaId = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', '/api/media', ['id' => $mediaId, 'path' => null]);

        $this->assertNoContentResponse();
        static::assertNotNull($this->findMedia($mediaId));
    }

    public function testUpdatingPathOfExistingMediaIsRejected(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');

        $this->getBrowser()->jsonRequest('PATCH', '/api/media/' . $mediaId, [
            'private' => true,
            'path' => $this->victimPath,
        ]);

        $this->assertIllegalPathResponse();
        $media = $this->findMedia($mediaId);
        static::assertSame('media/ab/cd/legit.png', $media?->getPath());
        static::assertFalse($media->isPrivate());
    }

    public function testResettingPathToNullIsRejected(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');

        $this->getBrowser()->jsonRequest('PATCH', '/api/media/' . $mediaId, [
            'path' => null,
        ]);

        $this->assertIllegalPathResponse();
        static::assertSame('media/ab/cd/legit.png', $this->findMedia($mediaId)?->getPath());
    }

    public function testUpdatingThumbnailPathIsRejected(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');
        $thumbnailId = $this->createThumbnail($mediaId, path: 'thumbnail/ab/cd/legit_100x100.png');

        $this->getBrowser()->jsonRequest('PATCH', '/api/media-thumbnail/' . $thumbnailId, [
            'path' => $this->victimPath,
        ]);

        $this->assertIllegalPathResponse();
        static::assertSame('thumbnail/ab/cd/legit_100x100.png', $this->findThumbnailPath($thumbnailId));
    }

    public function testUpdatingThumbnailPathThroughMediaAssociationIsRejected(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');
        $thumbnailId = $this->createThumbnail($mediaId, path: 'thumbnail/ab/cd/legit_100x100.png');

        $this->getBrowser()->jsonRequest('PATCH', '/api/media/' . $mediaId, [
            'thumbnails' => [
                ['id' => $thumbnailId, 'path' => $this->victimPath],
            ],
        ]);

        $this->assertIllegalPathResponse();
        static::assertSame('thumbnail/ab/cd/legit_100x100.png', $this->findThumbnailPath($thumbnailId));
    }

    public function testUpdatingPathThroughSyncApiIsRejected(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');

        $this->getBrowser()->jsonRequest('POST', '/api/_action/sync', [[
            'key' => 'write-media',
            'action' => 'upsert',
            'entity' => 'media',
            'payload' => [['id' => $mediaId, 'path' => $this->victimPath]],
        ]]);

        $this->assertIllegalPathResponse();
        static::assertSame('media/ab/cd/legit.png', $this->findMedia($mediaId)?->getPath());
    }

    public function testWritingBackUnchangedRelativePathIsAllowed(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');

        $this->getBrowser()->jsonRequest('PATCH', '/api/media/' . $mediaId, [
            'path' => 'media/ab/cd/legit.png',
            'title' => 'updated',
        ]);

        $this->assertNoContentResponse();
        static::assertSame('updated', $this->findMedia($mediaId)?->getTitle());
    }

    public function testWritingBackUnchangedRelativePathThroughSyncApiIsAllowed(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');
        $thumbnailId = $this->createThumbnail($mediaId, path: 'thumbnail/ab/cd/legit_100x100.png');

        $this->getBrowser()->jsonRequest('POST', '/api/_action/sync', [[
            'key' => 'write-media',
            'action' => 'upsert',
            'entity' => 'media',
            'payload' => [[
                'id' => $mediaId,
                'path' => 'media/ab/cd/legit.png',
                'thumbnails' => [['id' => $thumbnailId, 'path' => 'thumbnail/ab/cd/legit_100x100.png']],
            ]],
        ]]);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
    }

    public function testUpdatingThumbnailPathToExternalUrlIsAllowed(): void
    {
        $mediaId = $this->createMedia(path: 'https://cdn.example.com/photo/sunflowers.jpg');
        $thumbnailId = $this->createThumbnail($mediaId, path: 'https://cdn.example.com/photo/sunflowers_100x100.jpg');

        $this->getBrowser()->jsonRequest('PATCH', '/api/media-thumbnail/' . $thumbnailId, [
            'path' => 'https://cdn.example.com/photo/sunflowers_small.jpg',
        ]);

        $this->assertNoContentResponse();
        static::assertSame('https://cdn.example.com/photo/sunflowers_small.jpg', $this->findThumbnailPath($thumbnailId));
    }

    public function testChangingExternalUrlOfExistingMediaIsAllowed(): void
    {
        $mediaId = $this->createMedia(path: 'https://cdn.example.com/photo/sunflowers.jpg');

        $this->getBrowser()->jsonRequest('PATCH', '/api/media/' . $mediaId, [
            'path' => 'https://cdn.example.com/photo/sunflowers_v2.jpg',
        ]);

        $this->assertNoContentResponse();
        static::assertSame('https://cdn.example.com/photo/sunflowers_v2.jpg', $this->findMedia($mediaId)?->getPath());
    }

    public function testUpdatingFileNameWithPathSegmentsIsRejected(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');

        $this->getBrowser()->jsonRequest('PATCH', '/api/media/' . $mediaId, [
            'fileName' => '../../../../evil',
            'fileExtension' => 'png',
        ]);

        $this->assertErrorResponse(self::ILLEGAL_FILE_NAME_CODE);
        static::assertSame('legit', $this->findMedia($mediaId)?->getFileName());
    }

    public function testTooLongFileNameIsRejectedWithItsOwnErrorCode(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');

        $this->getBrowser()->jsonRequest('PATCH', '/api/media/' . $mediaId, [
            'fileName' => str_repeat('a', 256),
        ]);

        $this->assertErrorResponse(MediaException::MEDIA_FILE_NAME_IS_TOO_LONG);
        static::assertSame('legit', $this->findMedia($mediaId)?->getFileName());
    }

    public function testWritingBackUnchangedFileNameThatPredatesTheValidationIsAllowed(): void
    {
        $mediaId = $this->createMedia(path: 'https://cdn.example.com/Ben%20%26%20Jerry%27s.jpg', fileName: 'Ben & Jerry\'s');

        $this->getBrowser()->jsonRequest('PATCH', '/api/media/' . $mediaId, [
            'fileName' => 'Ben & Jerry\'s',
            'title' => 'updated',
        ]);

        $this->assertNoContentResponse();
        static::assertSame('updated', $this->findMedia($mediaId)?->getTitle());
    }

    public function testWritingBackUnchangedFileNameThatPredatesTheValidationThroughSyncApiIsAllowed(): void
    {
        $mediaId = $this->createMedia(path: 'https://cdn.example.com/Ben%20%26%20Jerry%27s.jpg', fileName: 'Ben & Jerry\'s');

        $this->getBrowser()->jsonRequest('POST', '/api/_action/sync', [[
            'key' => 'write-media',
            'action' => 'upsert',
            'entity' => 'media',
            'payload' => [['id' => $mediaId, 'fileName' => 'Ben & Jerry\'s', 'title' => 'updated']],
        ]]);

        static::assertSame(Response::HTTP_OK, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertSame('updated', $this->findMedia($mediaId)?->getTitle());
    }

    public function testCreatingMediaWithFileNameWithPathSegmentsIsRejected(): void
    {
        $mediaId = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', '/api/media', [
            'id' => $mediaId,
            'fileName' => '../../theme/evil',
        ]);

        $this->assertErrorResponse(self::ILLEGAL_FILE_NAME_CODE);
        static::assertNull($this->findMedia($mediaId));
    }

    /**
     * @param array<string, string> $overwrites
     */
    #[DataProvider('mediaStorageOverwriteProvider')]
    public function testCloningMediaWithInvalidStorageOverwriteIsRejected(array $overwrites): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');
        $countBefore = $this->countMedia();

        $this->getBrowser()->jsonRequest('POST', '/api/_action/clone/media/' . $mediaId, [
            'overwrites' => $overwrites,
            'cloneChildren' => false,
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertSame($countBefore, $this->countMedia());
    }

    public static function mediaStorageOverwriteProvider(): \Generator
    {
        yield 'relative path' => [['path' => 'document/invoice.pdf']];
        yield 'file name with path segments' => [['fileName' => '../../theme/evil']];
    }

    public function testCloningMediaWithValidFileNameAndExternalUrlIsAllowed(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');

        $this->getBrowser()->jsonRequest('POST', '/api/_action/clone/media/' . $mediaId, [
            'overwrites' => ['fileName' => 'copied-media', 'path' => 'https://cdn.example.com/photo/copied-media.png'],
            'cloneChildren' => false,
        ]);

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $cloneId = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR)['id'];
        static::assertSame('https://cdn.example.com/photo/copied-media.png', $this->findMedia($cloneId)?->getPath());
    }

    public function testCloningThumbnailWithOverwrittenPathIsRejected(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');
        $thumbnailId = $this->createThumbnail($mediaId, path: 'thumbnail/ab/cd/legit_100x100.png');

        $this->getBrowser()->jsonRequest('POST', '/api/_action/clone/media-thumbnail/' . $thumbnailId, [
            'overwrites' => ['path' => 'document/invoice.pdf'],
        ]);

        static::assertSame(Response::HTTP_BAD_REQUEST, $this->getBrowser()->getResponse()->getStatusCode(), (string) $this->getBrowser()->getResponse()->getContent());
        static::assertFalse(static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT 1 FROM media_thumbnail WHERE path = :path',
            ['path' => 'document/invoice.pdf']
        ));
    }

    public function testDeletingMediaDoesNotDeleteForeignFileAfterRejectedPathUpdate(): void
    {
        $this->privateFilesystem->write($this->victimPath, 'confidential invoice');
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');

        $this->getBrowser()->jsonRequest('PATCH', '/api/media/' . $mediaId, [
            'private' => true,
            'path' => $this->victimPath,
        ]);
        $this->assertIllegalPathResponse();

        $this->getBrowser()->jsonRequest('DELETE', '/api/media/' . $mediaId);
        $this->assertNoContentResponse();
        $this->runWorker();

        static::assertTrue($this->privateFilesystem->fileExists($this->victimPath));
    }

    public function testCreatingMediaWithExternalUrlIsStillAllowed(): void
    {
        $mediaId = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', '/api/media', [
            'id' => $mediaId,
            'path' => 'https://cdn.example.com/photo/sunflowers.jpg',
            'fileName' => 'sunflowers',
            'mimeType' => 'image/jpeg',
        ]);

        $this->assertNoContentResponse();
        static::assertSame('https://cdn.example.com/photo/sunflowers.jpg', $this->findMedia($mediaId)?->getPath());
    }

    #[DataProvider('externalUrlsResolvingToLocalFilesProvider')]
    public function testCreatingMediaWithExternalUrlResolvingToLocalFileIsRejected(string $url): void
    {
        $mediaId = Uuid::randomHex();

        $this->getBrowser()->jsonRequest('POST', '/api/media', [
            'id' => $mediaId,
            'private' => true,
            'path' => $url,
        ]);

        $this->assertIllegalPathResponse();
        static::assertNull($this->findMedia($mediaId));
    }

    public static function externalUrlsResolvingToLocalFilesProvider(): \Generator
    {
        yield 'dot-dot segments climb out of the host segment' => ['https://cdn.example.com/../../document/invoice.pdf'];
        yield 'backslashes are normalized to dot-dot segments' => ['https://cdn.example.com\\..\\..\\document\\invoice.pdf'];
        yield 'url without host only needs one dot-dot segment' => ['https:///../document/invoice.pdf'];
    }

    public function testUploadingToExternalMediaDoesNotDeleteLocalFile(): void
    {
        $this->privateFilesystem->write($this->victimPath, 'confidential invoice');
        $mediaId = $this->createMedia(path: $this->traversalUrlToVictim, private: true);

        $this->getBrowser()->request(
            'POST',
            \sprintf('/api/_action/media/%s/upload?extension=png&fileName=replacement', $mediaId),
            [],
            [],
            [
                'HTTP_CONTENT-TYPE' => 'image/png',
                'HTTP_CONTENT-LENGTH' => filesize(self::TEST_IMAGE),
            ],
            (string) file_get_contents(self::TEST_IMAGE)
        );

        $this->assertNoContentResponse();
        static::assertTrue($this->privateFilesystem->fileExists($this->victimPath));

        $this->deleteUploadedFileOf($mediaId);
    }

    public function testRenamingExternalMediaOnlyRenamesWithoutMovingLocalFile(): void
    {
        $this->privateFilesystem->write($this->victimPath, 'confidential invoice');
        $mediaId = $this->createMedia(path: $this->traversalUrlToVictim, private: true);

        $this->getBrowser()->jsonRequest('POST', \sprintf('/api/_action/media/%s/rename', $mediaId), [
            'fileName' => 'renamed',
        ]);

        $this->assertNoContentResponse();
        static::assertTrue($this->privateFilesystem->fileExists($this->victimPath));

        $media = $this->findMedia($mediaId);
        static::assertSame('renamed', $media?->getFileName());
        static::assertSame($this->traversalUrlToVictim, $media->getPath());
    }

    public function testDownloadingPrivateExternalMediaDoesNotStreamLocalFile(): void
    {
        $this->privateFilesystem->write($this->victimPath, 'confidential invoice');
        $mediaId = $this->createMedia(path: $this->traversalUrlToVictim, private: true);
        $media = $this->findMedia($mediaId);
        static::assertNotNull($media);

        $this->expectExceptionObject(MediaException::missingFile($mediaId));

        static::getContainer()->get(DownloadResponseGenerator::class)->getResponseByContext($media, Context::createDefaultContext());
    }

    public function testLoadingExternalMediaDoesNotReadLocalFile(): void
    {
        $this->privateFilesystem->write($this->victimPath, 'confidential invoice');
        $mediaId = $this->createMedia(path: $this->traversalUrlToVictim, private: true);

        $this->expectExceptionObject(MediaException::missingFile($mediaId));

        static::getContainer()->get(MediaService::class)->loadFile($mediaId, Context::createDefaultContext());
    }

    public function testMailingExternalMediaAsAttachmentDoesNotReadLocalFile(): void
    {
        $publicFilesystem = static::getContainer()->get('shopware.filesystem.public');
        $victimPath = 'victim/' . Uuid::randomHex() . '.txt';
        $publicFilesystem->write($victimPath, 'confidential content');
        $localPath = 'media/' . Uuid::randomHex() . '/brochure.txt';
        $publicFilesystem->write($localPath, 'brochure content');
        $externalMediaId = $this->createMedia(path: 'https://cdn.example.com/../../' . $victimPath);
        $localMediaId = $this->createMedia(path: $localPath);

        $mail = null;
        EventHookDispatcher::fromContainer(static::getContainer())->on(
            MailBeforeSentEvent::class,
            static function (MailBeforeSentEvent $event) use (&$mail): void {
                $mail = $event->getMessage();
                $event->stopPropagation();
            }
        );

        try {
            static::getContainer()->get(MailService::class)->send([
                'recipients' => ['customer@example.com' => 'Customer'],
                'senderName' => 'Shop',
                'salesChannelId' => TestDefaults::SALES_CHANNEL,
                'subject' => 'Your files',
                'contentHtml' => '<p>Attached</p>',
                'contentPlain' => 'Attached',
                'mediaIds' => [$externalMediaId, $localMediaId],
            ], Context::createDefaultContext());

            static::assertInstanceOf(Mail::class, $mail);

            static::getContainer()->get(MailerTransportLoader::class)
                ->createTransportUsingDSN('null://null')
                ->send($mail);

            $attachments = $mail->getAttachments();
            static::assertCount(1, $attachments);
            static::assertSame('brochure content', $attachments[0]->getBody());
        } finally {
            $publicFilesystem->delete($victimPath);
            $publicFilesystem->delete($localPath);
        }
    }

    public function testSystemScopeCanStillWriteRelativeMediaPath(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');

        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, function (Context $context) use ($mediaId): void {
            $this->mediaRepository->update([['id' => $mediaId, 'path' => 'media/ef/gh/renamed.png']], $context);
        });

        static::assertSame('media/ef/gh/renamed.png', $this->findMedia($mediaId)?->getPath());
    }

    public function testSystemScopeCanStillWriteRelativeThumbnailPath(): void
    {
        $mediaId = $this->createMedia(path: 'media/ab/cd/legit.png');
        $thumbnailId = $this->createThumbnail($mediaId, path: 'thumbnail/ab/cd/legit_100x100.png');

        Context::createDefaultContext()->scope(Context::SYSTEM_SCOPE, static function (Context $context) use ($thumbnailId): void {
            static::getContainer()->get('media_thumbnail.repository')
                ->update([['id' => $thumbnailId, 'path' => 'thumbnail/ef/gh/renamed_100x100.png']], $context);
        });

        static::assertSame('thumbnail/ef/gh/renamed_100x100.png', $this->findThumbnailPath($thumbnailId));
    }

    private function assertIllegalPathResponse(): void
    {
        $this->assertErrorResponse(self::ILLEGAL_PATH_CODE);
    }

    private function assertErrorResponse(string $code): void
    {
        $response = $this->getBrowser()->getResponse();
        $content = (string) $response->getContent();

        static::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), $content);

        $responseData = json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        static::assertContains($code, array_column($responseData['errors'] ?? [], 'code'), $content);
    }

    private function assertNoContentResponse(): void
    {
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), (string) $response->getContent());
    }

    private function createMedia(string $path, bool $private = false, string $fileName = 'legit'): string
    {
        $mediaId = Uuid::randomHex();

        $this->mediaRepository->create([[
            'id' => $mediaId,
            'private' => $private,
            'fileName' => $fileName,
            'fileExtension' => 'png',
            'mimeType' => 'image/png',
            'path' => $path,
        ]], Context::createDefaultContext());

        return $mediaId;
    }

    private function createThumbnail(string $mediaId, string $path): string
    {
        $thumbnailId = Uuid::randomHex();

        static::getContainer()->get('media_thumbnail.repository')->create([[
            'id' => $thumbnailId,
            'mediaId' => $mediaId,
            'mediaThumbnailSize' => ['width' => 100, 'height' => 100],
            'width' => 100,
            'height' => 100,
            'path' => $path,
        ]], Context::createDefaultContext());

        return $thumbnailId;
    }

    private function deleteUploadedFileOf(string $mediaId): void
    {
        $path = $this->findMedia($mediaId)?->getPath();

        if (\is_string($path) && !str_starts_with($path, 'http') && $this->privateFilesystem->fileExists($path)) {
            $this->privateFilesystem->delete($path);
        }
    }

    private function countMedia(): int
    {
        return (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM media');
    }

    private function findMedia(string $mediaId): ?MediaEntity
    {
        return $this->mediaRepository->search(new Criteria([$mediaId]), Context::createDefaultContext())->getEntities()->first();
    }

    private function findThumbnailPath(string $thumbnailId): ?string
    {
        $path = static::getContainer()->get('media_thumbnail.repository')
            ->search(new Criteria([$thumbnailId]), Context::createDefaultContext())
            ->getEntities()
            ->first()
            ?->get('path');

        return \is_string($path) ? $path : null;
    }
}

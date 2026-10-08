<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Api;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize\MediaThumbnailSizeCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\DevOps\Environment\EnvironmentHelper;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
#[Group('needsWebserver')]
class MediaUploadV2ControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    private const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    private const TEST_IMAGE_NAME = 'media-upload-v2-acl-test.png';

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<MediaThumbnailCollection>
     */
    private EntityRepository $mediaThumbnailRepository;

    /**
     * @var EntityRepository<MediaThumbnailSizeCollection>
     */
    private EntityRepository $mediaThumbnailSizeRepository;

    private Filesystem $filesystem;

    private bool $mediaDirCreated = false;

    protected function setUp(): void
    {
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->mediaThumbnailRepository = static::getContainer()->get('media_thumbnail.repository');
        $this->mediaThumbnailSizeRepository = static::getContainer()->get('media_thumbnail_size.repository');

        /** @var MockHttpClient $httpClient */
        $httpClient = static::getContainer()->get('shopware.media.upload.http_client');
        $httpClient->setResponseFactory(
            static fn () => new MockResponse(
                '',
                [
                    'http_code' => 200,
                    'response_headers' => [
                        'content-length' => ['12345'],
                        'content-type' => ['image/jpeg'],
                    ],
                ]
            )
        );

        $this->filesystem = new Filesystem();
        $mediaDir = static::getContainer()->getParameter('kernel.project_dir') . '/public/media';
        if (!\is_dir($mediaDir)) {
            $this->filesystem->mkdir($mediaDir);
            $this->mediaDirCreated = true;
        }
        $this->filesystem->copy(self::TEST_IMAGE, $mediaDir . '/' . self::TEST_IMAGE_NAME, true);
    }

    protected function tearDown(): void
    {
        $mediaDir = static::getContainer()->getParameter('kernel.project_dir') . '/public/media';
        $this->filesystem->remove($mediaDir . '/' . self::TEST_IMAGE_NAME);

        if ($this->mediaDirCreated) {
            $this->filesystem->remove($mediaDir);
            $this->mediaDirCreated = false;
        }
    }

    public function testExternalLinkWithThumbnailsCreatesMediaAndPersistsThumbnails(): void
    {
        $context = Context::createDefaultContext();

        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_action/media/external-link',
            [
                'url' => 'https://localhost:8000/Geschenkt%C3%BCte.jpg',
                'mimeType' => 'image/jpeg',
                'thumbnails' => [
                    ['url' => 'https://localhost:8000/Geschenkt%C3%BCte-200.jpg', 'width' => 200, 'height' => 150],
                    ['url' => 'https://localhost:8000/Geschenkt%C3%BCte-400.jpg', 'width' => 400, 'height' => 300],
                ],
            ],
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $responseData = json_decode((string) $response->getContent(), true);
        static::assertArrayHasKey('id', $responseData);
        $mediaId = $responseData['id'];

        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $context)->getEntities()->first();

        static::assertNotNull($media);
        static::assertSame('https://localhost:8000/Geschenktüte.jpg', $media->getPath());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mediaId', $mediaId));
        $thumbnails = $this->mediaThumbnailRepository->search($criteria, $context);

        static::assertSame(2, $thumbnails->getTotal());

        $urls = $thumbnails->getEntities()->map(static fn ($t) => $t->getPath());
        static::assertContains('https://localhost:8000/Geschenktüte-200.jpg', $urls);
        static::assertContains('https://localhost:8000/Geschenktüte-400.jpg', $urls);
    }

    public function testExternalLinkRequiresMediaCreatePrivilege(): void
    {
        $browser = $this->getBrowser(true, [], ['media:read']);
        $browser->jsonRequest(
            'POST',
            '/api/_action/media/external-link',
            [
                'url' => 'https://localhost:8000/example.html',
                'mimeType' => 'text/html',
            ],
        );

        $content = (string) $browser->getResponse()->getContent();

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $content);
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, json_decode($content, true)['errors'][0]['code'], $content);
        static::assertSame(['media:create'], json_decode(json_decode($content, true)['errors'][0]['detail'], true)['missingPrivileges'], $content);
    }

    public function testUploadRequiresMediaCreatePrivilege(): void
    {
        $browser = $this->getBrowser(true, [], ['media:read']);
        $browser->request(
            'POST',
            '/api/_action/media/upload',
            ['fileName' => self::TEST_IMAGE_NAME],
            ['file' => new UploadedFile(self::TEST_IMAGE, self::TEST_IMAGE_NAME, 'image/png', null, true)],
        );

        $content = (string) $browser->getResponse()->getContent();

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $content);
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, json_decode($content, true)['errors'][0]['code'], $content);
        static::assertSame(['media:create'], json_decode(json_decode($content, true)['errors'][0]['detail'], true)['missingPrivileges'], $content);
    }

    public function testUploadByUrlRequiresMediaCreatePrivilege(): void
    {
        $browser = $this->getBrowser(true, [], ['media:read']);
        $browser->jsonRequest(
            'POST',
            '/api/_action/media/upload_by_url',
            [
                'url' => EnvironmentHelper::getVariable('APP_URL') . '/media/' . self::TEST_IMAGE_NAME,
                'mimeType' => 'image/png',
            ],
        );

        $content = (string) $browser->getResponse()->getContent();

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $content);
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, json_decode($content, true)['errors'][0]['code'], $content);
        static::assertSame(['media:create'], json_decode(json_decode($content, true)['errors'][0]['detail'], true)['missingPrivileges'], $content);
    }

    public function testAddingExternalThumbnailsRequiresMediaThumbnailCreatePrivilege(): void
    {
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([[
            'id' => $mediaId,
            'path' => 'https://localhost:8000/image.jpg',
            'mimeType' => 'image/jpeg',
            'fileExtension' => 'jpg',
        ]], Context::createDefaultContext());
        $browser = $this->getBrowser(true, [], ['media:read']);
        $browser->jsonRequest(
            'POST',
            '/api/_action/media/' . $mediaId . '/external-thumbnails',
            ['thumbnails' => [['url' => 'https://localhost:8000/image-200.jpg', 'width' => 200, 'height' => 150]]],
        );

        $content = (string) $browser->getResponse()->getContent();

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $content);
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, json_decode($content, true)['errors'][0]['code'], $content);
        static::assertSame(['media_thumbnail:create'], json_decode(json_decode($content, true)['errors'][0]['detail'], true)['missingPrivileges'], $content);
    }

    public function testDeletingExternalThumbnailsRequiresMediaThumbnailDeletePrivilege(): void
    {
        $mediaId = Uuid::randomHex();
        $this->mediaRepository->create([[
            'id' => $mediaId,
            'path' => 'https://localhost:8000/image.jpg',
            'mimeType' => 'image/jpeg',
            'fileExtension' => 'jpg',
        ]], Context::createDefaultContext());
        $thumbnailSizeId = Uuid::randomHex();
        $this->mediaThumbnailSizeRepository->create([[
            'id' => $thumbnailSizeId,
            'width' => 200,
            'height' => 150,
        ]], Context::createDefaultContext());
        $this->mediaThumbnailRepository->create([[
            'id' => Uuid::randomHex(),
            'mediaId' => $mediaId,
            'mediaThumbnailSizeId' => $thumbnailSizeId,
            'width' => 200,
            'height' => 150,
            'path' => 'https://localhost:8000/image-200.jpg',
        ]], Context::createDefaultContext());

        $browser = $this->getBrowser(true, [], ['media:read']);
        $browser->jsonRequest('DELETE', '/api/_action/media/' . $mediaId . '/external-thumbnails');

        $content = (string) $browser->getResponse()->getContent();

        static::assertSame(Response::HTTP_FORBIDDEN, $browser->getResponse()->getStatusCode(), $content);
        static::assertSame(MissingPrivilegeException::MISSING_PRIVILEGE_ERROR, json_decode($content, true)['errors'][0]['code'], $content);
        static::assertSame(['media_thumbnail:delete'], json_decode(json_decode($content, true)['errors'][0]['detail'], true)['missingPrivileges'], $content);
    }

    public function testAddAndDeleteExternalThumbnailsForMedia(): void
    {
        $mediaId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $this->mediaRepository->create([[
            'id' => $mediaId,
            'path' => 'https://localhost:8000/image.jpg',
            'mimeType' => 'image/jpeg',
            'fileExtension' => 'jpg',
        ]], $context);

        $this->getBrowser()->jsonRequest(
            'POST',
            '/api/_action/media/' . $mediaId . '/external-thumbnails',
            [
                'thumbnails' => [
                    ['url' => 'https://localhost:8000/image-200.jpg', 'width' => 200, 'height' => 150],
                    ['url' => 'https://localhost:8000/image-400.jpg', 'width' => 400, 'height' => 300],
                ],
            ],
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), (string) $response->getContent());

        $responseData = json_decode((string) $response->getContent(), true);
        static::assertSame($mediaId, $responseData['mediaId']);
        static::assertSame(2, $responseData['thumbnailsCreated']);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mediaId', $mediaId));
        static::assertSame(2, $this->mediaThumbnailRepository->search($criteria, $context)->getTotal());

        $this->getBrowser()->jsonRequest(
            'DELETE',
            '/api/_action/media/' . $mediaId . '/external-thumbnails',
        );

        $response = $this->getBrowser()->getResponse();
        static::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string) $response->getContent());

        $responseData = json_decode((string) $response->getContent(), true);
        static::assertSame($mediaId, $responseData['mediaId']);

        static::assertSame(0, $this->mediaThumbnailRepository->search($criteria, $context)->getTotal());
    }
}

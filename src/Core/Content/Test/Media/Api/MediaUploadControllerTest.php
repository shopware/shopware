<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class MediaUploadControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour, MediaFixtures;

    public const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    /** @var EntityRepositoryInterface */
    private $mediaRepository;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var string */
    private $mediaId;

    /** @var Context */
    private $context;

    protected function setUp()
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $this->context = Context::createDefaultContext();
        $this->mediaId = $this->getEmptyMedia()->getId();
    }

    public function testUploadFromBinaryUsesMediaId(): void
    {
        $url = sprintf(
            '/api/v%s/_action/media/%s/upload',
            PlatformRequest::API_VERSION,
            $this->mediaId
        );

        $this->getClient()->request(
            'POST',
            $url . '?extension=png',
            [],
            [],
            [
                'HTTP_CONTENT-TYPE' => 'image/png',
                'HTTP_CONTENT-LENGTH' => filesize(self::TEST_IMAGE),
            ],
            file_get_contents(self::TEST_IMAGE)
        );
        $media = $this->mediaRepository->search(new Criteria([$this->mediaId]), $this->context)->get($this->mediaId);

        $response = $this->getClient()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));
        static::assertStringEndsWith(
            '/api/v' . PlatformRequest::API_VERSION . '/media/' . $this->mediaId,
            $response->headers->get('Location')
        );

        $mediaPath = $this->urlGenerator->getRelativeMediaUrl($media);
        static::assertTrue($this->getPublicFilesystem()->has($mediaPath));
        static::assertStringEndsWith($media->getId() . '.' . $media->getFileExtension(), $mediaPath);

        $this->getClient()->request(
            'GET',
            "/api/v1/media/{$this->mediaId}"
        );

        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertCount(
            2,
            $responseData['data']['attributes']['metaData'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertSame(
            266,
            $responseData['data']['attributes']['metaData']['type']['width'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertCount(
            2,
            $responseData['data']['attributes']['mediaType'],
            print_r($responseData['data']['attributes']['mediaType'], true)
        );
        static::assertSame(
            'IMAGE',
            $responseData['data']['attributes']['mediaType']['name'],
            print_r($responseData['data']['attributes']['mediaType'], true)
        );
        static::assertCount(
            1,
            $responseData['data']['attributes']['mediaType']['flags'],
            print_r($responseData['data']['attributes']['mediaType']['flags'], true)
        );
        static::assertSame(
            ImageType::TRANSPARENT,
            $responseData['data']['attributes']['mediaType']['flags'][0],
            print_r($responseData['data']['attributes']['mediaType']['flags'], true)
        );
    }

    public function testUploadFromBinaryUsesFileName(): void
    {
        $url = sprintf(
            '/api/v%s/_action/media/%s/upload',
            PlatformRequest::API_VERSION,
            $this->mediaId
        );

        $this->getClient()->request(
            'POST',
            $url . '?extension=png&fileName=new%20file%20name',
            [],
            [],
            [
                'HTTP_CONTENT-TYPE' => 'image/png',
                'HTTP_CONTENT-LENGTH' => filesize(self::TEST_IMAGE),
            ],
            file_get_contents(self::TEST_IMAGE)
        );
        $media = $this->mediaRepository->search(new Criteria([$this->mediaId]), $this->context)->get($this->mediaId);
        $mediaPath = $this->urlGenerator->getRelativeMediaUrl($media);
        $response = $this->getClient()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));
        static::assertStringEndsWith(
            '/api/v' . PlatformRequest::API_VERSION . '/media/' . $this->mediaId,
            $response->headers->get('Location')
        );

        static::assertTrue($this->getPublicFilesystem()->has($mediaPath));
        static::assertStringEndsWith('new file name', $media->getFileName());

        $this->getClient()->request(
            'GET',
            "/api/v1/media/{$this->mediaId}"
        );

        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertCount(
            2,
            $responseData['data']['attributes']['metaData'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertSame(
            266,
            $responseData['data']['attributes']['metaData']['type']['width'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertCount(
            2,
            $responseData['data']['attributes']['mediaType'],
            print_r($responseData['data']['attributes']['mediaType'], true)
        );
        static::assertSame(
            'IMAGE',
            $responseData['data']['attributes']['mediaType']['name'],
            print_r($responseData['data']['attributes']['mediaType'], true)
        );
        static::assertCount(
            1,
            $responseData['data']['attributes']['mediaType']['flags'],
            print_r($responseData['data']['attributes']['mediaType']['flags'], true)
        );
        static::assertSame(
            ImageType::TRANSPARENT,
            $responseData['data']['attributes']['mediaType']['flags'][0],
            print_r($responseData['data']['attributes']['mediaType']['flags'], true)
        );
    }

    public function testUploadFromURL(): void
    {
        $target = $this->getContainer()->getParameter('kernel.project_dir') . '/public/shopware-logo.png';
        copy(__DIR__ . '/../fixtures/shopware-logo.png', $target);

        $baseUrl = getenv('APP_URL');

        $url = sprintf(
            '/api/v%s/_action/media/%s/upload',
            PlatformRequest::API_VERSION,
            $this->mediaId
        );

        try {
            $this->getClient()->request(
                 'POST',
                $url . '?extension=png',
                 [],
                 [],
                 [
                     'HTTP_CONTENT-TYPE' => 'application/json',
                 ],
                 json_encode(['url' => $baseUrl . '/shopware-logo.png'])
             );
            $response = $this->getClient()->getResponse();
        } finally {
            unlink($target);
        }

        $media = $this->mediaRepository->search(new Criteria([$this->mediaId]), $this->context)->get($this->mediaId);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertStringEndsWith(
            '/api/v' . PlatformRequest::API_VERSION . '/media/' . $this->mediaId,
            $response->headers->get('Location')
        );
        static::assertTrue($this->getPublicFilesystem()->has($this->urlGenerator->getRelativeMediaUrl($media)));

        $this->getClient()->request(
            'GET',
            "/api/v1/media/{$this->mediaId}"
        );

        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertCount(
            2,
            $responseData['data']['attributes']['metaData'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertSame(
            266,
            $responseData['data']['attributes']['metaData']['type']['width'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertCount(
            2,
            $responseData['data']['attributes']['mediaType'],
            print_r($responseData['data']['attributes']['mediaType'], true)
        );
        static::assertSame(
            'IMAGE',
            $responseData['data']['attributes']['mediaType']['name'],
            print_r($responseData['data']['attributes']['mediaType'], true)
        );
        static::assertCount(
            1,
            $responseData['data']['attributes']['mediaType']['flags'],
            print_r($responseData['data']['attributes']['mediaType']['flags'], true)
        );
        static::assertSame(
            ImageType::TRANSPARENT,
            $responseData['data']['attributes']['mediaType']['flags'][0],
            print_r($responseData['data']['attributes']['mediaType']['flags'], true)
        );
    }

    public function testRenameMediaFileThrowsExceptionIfFileNameIsNotPresent()
    {
        $context = Context::createDefaultContext();
        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $this->setFixtureContext($context);
        $media = $this->getPng();
        $context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_META_INFO);

        $url = sprintf(
            '/api/v%s/_action/media/%s/rename',
            PlatformRequest::API_VERSION,
            $media->getId()
        );

        $this->getClient()->request(
            'POST',
            $url,
            [],
            [],
            [
                'HTTP_CONTENT_TYPE' => 'application/json',
            ],
            \json_encode([])
        );

        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);

        static::assertEquals(400, $response->getStatusCode());
        static::assertEquals('EMPTY_MEDIA_FILE_EXCEPTION', $responseData['errors'][0]['code']);
    }

    public function testRenameMediaFileHappyPath()
    {
        $context = Context::createDefaultContext();
        $context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);
        $this->setFixtureContext($context);
        $media = $this->getPng();
        $context->getWriteProtection()->disallow(MediaProtectionFlags::WRITE_META_INFO);

        $this->getPublicFilesystem()->put($this->urlGenerator->getRelativeMediaUrl($media), 'some content');

        $url = sprintf(
            '/api/v%s/_action/media/%s/rename',
            PlatformRequest::API_VERSION,
            $media->getId()
        );

        $this->getClient()->request(
            'POST',
            $url,
            [],
            [],
            [
                'HTTP_CONTENT_TYPE' => 'application/json',
            ],
            \json_encode(['fileName' => 'new file name'])
        );

        $response = $this->getClient()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $updatedMedia = $this->mediaRepository->search(new Criteria([$media->getId()]), $context)->get($media->getId());
        self::assertNotEquals($media->getFileName(), $updatedMedia->getFileName());
        self::assertTrue($this->getPublicFilesystem()->has($this->urlGenerator->getRelativeMediaUrl($updatedMedia)));
    }
}

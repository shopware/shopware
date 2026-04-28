<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaThumbnail\MediaThumbnailCollection;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('discovery')]
class MediaUploadV2ControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    /**
     * @var EntityRepository<MediaCollection>
     */
    private EntityRepository $mediaRepository;

    /**
     * @var EntityRepository<MediaThumbnailCollection>
     */
    private EntityRepository $mediaThumbnailRepository;

    protected function setUp(): void
    {
        $this->mediaRepository = static::getContainer()->get('media.repository');
        $this->mediaThumbnailRepository = static::getContainer()->get('media_thumbnail.repository');

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

        $media = $this->mediaRepository->search(new Criteria([$mediaId]), $context)->first();

        static::assertNotNull($media);
        static::assertSame('https://localhost:8000/Geschenktüte.jpg', $media->getPath());

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('mediaId', $mediaId));
        $thumbnails = $this->mediaThumbnailRepository->search($criteria, $context);

        static::assertSame(2, $thumbnails->getTotal());

        $urls = $thumbnails->map(static fn ($t) => $t->getPath());
        static::assertContains('https://localhost:8000/Geschenktüte-200.jpg', $urls);
        static::assertContains('https://localhost:8000/Geschenktüte-400.jpg', $urls);
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

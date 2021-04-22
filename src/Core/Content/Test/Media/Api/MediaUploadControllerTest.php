<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaType\ImageType;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Symfony\Component\HttpFoundation\Response;

class MediaUploadControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use MediaFixtures;

    public const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * @var string
     */
    private $mediaId;

    /**
     * @var Context
     */
    private $context;

    protected function setUp(): void
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $this->context = Context::createDefaultContext();
        $this->mediaId = $this->getEmptyMedia()->getId();
    }

    public function testUploadFromBinaryUsesMediaId(): void
    {
        $url = sprintf(
            '/api/_action/media/%s/upload',
            $this->mediaId
        );

        $this->getBrowser()->request(
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
        $media = $this->getMediaEntity();

        $mediaPath = $this->urlGenerator->getRelativeMediaUrl($media);
        static::assertTrue($this->getPublicFilesystem()->has($mediaPath));
        static::assertStringEndsWith($media->getId() . '.' . $media->getFileExtension(), $mediaPath);

        $this->assertMediaApiResponse();
    }

    public function testUploadFromBinaryUsesFileName(): void
    {
        $url = sprintf(
            '/api/_action/media/%s/upload',
            $this->mediaId
        );

        $this->getBrowser()->request(
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
        $media = $this->getMediaEntity();

        $mediaPath = $this->urlGenerator->getRelativeMediaUrl($media);
        static::assertTrue($this->getPublicFilesystem()->has($mediaPath));
        static::assertStringEndsWith('new file name', $media->getFileName());

        $this->assertMediaApiResponse();
    }

    public function testUploadFromURL(): void
    {
        $baseUrl = 'http://assets.shopware.com/sw_logo_white.png';

        $url = sprintf(
            '/api/_action/media/%s/upload',
            $this->mediaId
        );

        $this->getBrowser()->request(
            'POST',
            $url . '?extension=png',
            [],
            [],
            [
                'HTTP_CONTENT-TYPE' => 'application/json',
            ],
            json_encode(['url' => $baseUrl])
        );
        $response = $this->getBrowser()->getResponse();

        $media = $this->mediaRepository->search(new Criteria([$this->mediaId]), $this->context)->get($this->mediaId);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertStringEndsWith(
            '/api/media/' . $this->mediaId,
            $response->headers->get('Location')
        );
        static::assertTrue($this->getPublicFilesystem()->has($this->urlGenerator->getRelativeMediaUrl($media)));

        $this->assertMediaApiResponse(800);
    }

    public function testRenameMediaFileThrowsExceptionIfFileNameIsNotPresent(): void
    {
        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);
        $media = $this->getPng();

        $url = sprintf(
            '/api/_action/media/%s/rename',
            $media->getId()
        );

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            [
                'HTTP_CONTENT_TYPE' => 'application/json',
            ],
            json_encode([])
        );

        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode($response->getContent(), true);

        static::assertEquals(400, $response->getStatusCode());
        static::assertEquals('CONTENT__MEDIA_EMPTY_FILE', $responseData['errors'][0]['code']);
    }

    public function testRenameMediaFileHappyPath(): void
    {
        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);
        $media = $this->getPng();

        $this->getPublicFilesystem()->put($this->urlGenerator->getRelativeMediaUrl($media), 'some content');

        $url = sprintf(
            '/api/_action/media/%s/rename',
            $media->getId()
        );

        $this->getBrowser()->request(
            'POST',
            $url,
            [],
            [],
            [
                'HTTP_CONTENT_TYPE' => 'application/json',
            ],
            json_encode(['fileName' => 'new file name'])
        );

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(204, $response->getStatusCode());

        $updatedMedia = $this->mediaRepository->search(new Criteria([$media->getId()]), $context)->get($media->getId());
        static::assertNotEquals($media->getFileName(), $updatedMedia->getFileName());
        static::assertTrue($this->getPublicFilesystem()->has($this->urlGenerator->getRelativeMediaUrl($updatedMedia)));
    }

    public function testProvideName(): void
    {
        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);
        $media = $this->getPng();

        $url = sprintf(
            '/api/_action/media/provide-name?fileName=%s&extension=png',
            $media->getFileName()
        );

        $this->getBrowser()->request(
            'GET',
            $url
        );

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $result = json_decode($response->getContent(), true);
        static::assertEquals($media->getFileName() . '_(1)', $result['fileName']);
    }

    public function testProvideNameProvidesOwnName(): void
    {
        $context = Context::createDefaultContext();
        $this->setFixtureContext($context);
        $media = $this->getPng();

        $url = sprintf(
            '/api/_action/media/provide-name?fileName=%s&extension=png&mediaId=%s',
            $media->getFileName(),
            $media->getId()
        );

        $this->getBrowser()->request(
            'GET',
            $url
        );

        $response = $this->getBrowser()->getResponse();
        static::assertEquals(200, $response->getStatusCode());

        $result = json_decode($response->getContent(), true);
        static::assertEquals($media->getFileName(), $result['fileName']);
    }

    private function getMediaEntity(): MediaEntity
    {
        /** @var MediaEntity $media */
        $media = $this->mediaRepository->search(new Criteria([$this->mediaId]), $this->context)->get($this->mediaId);
        $response = $this->getBrowser()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));
        static::assertStringEndsWith(
            '/api/media/' . $this->mediaId,
            $response->headers->get('Location')
        );

        return $media;
    }

    private function assertMediaApiResponse(int $width = 499): void
    {
        $this->getBrowser()->request(
            'GET',
            '/api/media/' . $this->mediaId
        );

        $responseData = json_decode($this->getBrowser()->getResponse()->getContent(), true);

        static::assertCount(
            3,
            $responseData['data']['attributes']['metaData'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertSame(
            $width,
            $responseData['data']['attributes']['metaData']['width'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertCount(
            3,
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
}

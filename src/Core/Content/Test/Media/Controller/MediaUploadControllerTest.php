<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Controller;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class MediaUploadControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour, MediaFixtures;

    public const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    /** @var RepositoryInterface */
    private $mediaRepository;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var string */
    private $mediaId;

    protected function setUp()
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $this->mediaId = $this->getEmptyMedia()->getId();
    }

    public function testUploadFromBinary(): void
    {
        $path = $this->urlGenerator->getAbsoluteMediaUrl($this->mediaId, 'png');
        static::assertFalse($this->getPublicFilesystem()->has($path));

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
        $response = $this->getClient()->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));
        static::assertStringEndsWith(
            '/api/v' . PlatformRequest::API_VERSION . '/media/' . $this->mediaId,
            $response->headers->get('Location')
        );

        static::assertTrue($this->getPublicFilesystem()->has($path));
    }

    public function testUploadFromURL(): void
    {
        $path = $this->urlGenerator->getAbsoluteMediaUrl($this->mediaId, 'png');

        static::assertFalse($this->getPublicFilesystem()->has($path));

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

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertStringEndsWith(
            '/api/v' . PlatformRequest::API_VERSION . '/media/' . $this->mediaId,
            $response->headers->get('Location')
        );
        static::assertTrue($this->getPublicFilesystem()->has($path));

        $this->getClient()->request(
            'GET',
            "/api/v1/media/{$this->mediaId}"
        );

        $responseData = json_decode($this->getClient()->getResponse()->getContent(), true);

        static::assertCount(
            3,
            $responseData['data']['attributes']['metaData'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertSame(
            266,
            $responseData['data']['attributes']['metaData']['type']['width'],
            print_r($responseData['data']['attributes'], true)
        );
        static::assertSame(
            'image',
            $responseData['data']['attributes']['metaData']['typeName'],
            print_r($responseData['data']['attributes'], true)
        );
    }
}

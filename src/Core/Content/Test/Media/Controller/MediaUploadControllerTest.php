<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Controller;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use PHPUnit\Framework\TestCase;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Content\Media\Pathname\UrlGeneratorInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class MediaUploadControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;

    const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';

    /** @var RepositoryInterface */
    private $mediaRepository;

    /** @var FilesystemInterface */
    private $filesystem;

    /** @var UrlGeneratorInterface */
    private $urlGenerator;

    /** @var string */
    private $mediaId;

    protected function setUp()
    {
        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->filesystem = $this->getContainer()->get('shopware.filesystem.public');
        $this->urlGenerator = $this->getContainer()->get(UrlGeneratorInterface::class);

        $this->mediaId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->mediaRepository->create(
            [
                [
                    'id' => $this->mediaId,
                    'name' => 'test file',
                ],
            ],
            $context
        );
    }

    public function tearDown()
    {
        $path = $this->urlGenerator->getAbsoluteMediaUrl($this->mediaId, 'png');
        $path = pathinfo($path, PATHINFO_DIRNAME);

        try {
            $this->filesystem->deleteDir($path);
        } catch (FileNotFoundException $e) {
        }

        $path = preg_replace('/media/', 'thumbnail', $path);
        try {
            $this->filesystem->deleteDir($path);
        } catch (FileNotFoundException $e) {
        }

        parent::tearDown();
    }

    public function testUploadFromBinary(): void
    {
        $path = $this->urlGenerator->getAbsoluteMediaUrl($this->mediaId, 'png');
        static::assertFalse($this->filesystem->has($path));

        $this->getClient()->request(
            'POST',
            "/api/v1/media/{$this->mediaId}/actions/upload?extension=png",
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
        static::assertEquals(
            'http://localhost/api/v' . PlatformRequest::API_VERSION . '/media/' . $this->mediaId,
            $response->headers->get('Location')
        );
        static::assertTrue($this->filesystem->has($path));
    }

    public function testUploadFromURL(): void
    {
        $path = $this->urlGenerator->getAbsoluteMediaUrl($this->mediaId, 'png');
        static::assertFalse($this->filesystem->has($path));

        $target = $this->getContainer()->getParameter('kernel.project_dir') . '/public/shopware-logo.png';
        copy(__DIR__ . '/../fixtures/shopware-logo.png', $target);

        $url = getenv('APP_URL');

        try {
            $this->getClient()->request(
                 'POST',
                 "/api/v1/media/{$this->mediaId}/actions/upload?extension=png",
                 [],
                 [],
                 [
                     'HTTP_CONTENT-TYPE' => 'application/json',
                 ],
                 json_encode(['url' => $url . '/shopware-logo.png'])
             );
            $response = $this->getClient()->getResponse();
        } finally {
            unlink($target);
        }

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals(
            'http://localhost/api/v' . PlatformRequest::API_VERSION . '/media/' . $this->mediaId,
            $response->headers->get('Location')
        );
        static::assertTrue($this->filesystem->has($path));

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

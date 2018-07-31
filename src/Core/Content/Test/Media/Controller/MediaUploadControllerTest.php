<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Controller;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Ramsey\Uuid\Uuid;
use Shopware\Core\Content\Media\Util\Strategy\StrategyInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\Test\Api\ApiTestCase;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Response;

class MediaUploadControllerTest extends ApiTestCase
{
    const TEST_IMAGE = __DIR__ . '/../fixtures/shopware-logo.png';
    /** @var RepositoryInterface */
    private $mediaRepository;

    /** @var FilesystemInterface */
    private $filesystem;

    /** @var StrategyInterface */
    private $strategy;

    /** @var string */
    private $mediaId;

    protected function setUp()
    {
        parent::setUp();

        $this->mediaRepository = $this->getContainer()->get('media.repository');
        $this->filesystem = $this->getContainer()->get('shopware.filesystem.public');
        $this->strategy = $this->getContainer()->get(StrategyInterface::class);

        $this->mediaId = Uuid::uuid4()->getHex();
        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $this->mediaRepository->create(
            [
                [
                    'id' => $this->mediaId,
                    'name' => 'test file',
                    'album' => [
                        'id' => Uuid::uuid4()->getHex(),
                        'name' => 'test',
                    ],
                ],
            ],
            $context
        );
    }

    public function tearDown()
    {
        $path = $this->strategy->encode($this->mediaId);
        try {
            $this->filesystem->delete('media/' . $path . '.png');
            $this->filesystem->delete('media/' . $path . '.jpg');
        } catch (FileNotFoundException $e) {
        }
    }

    public function testUploadFromBinary(): void
    {
        $path = $this->strategy->encode($this->mediaId);
        static::assertFalse($this->filesystem->has('media/' . $path . '.png'));

        $this->apiClient->request(
            'POST',
            "/api/v1/media/{$this->mediaId}/actions/upload",
            [],
            [],
            [
                'HTTP_CONTENT-TYPE' => 'image/png',
                'HTTP_CONTENT-LENGTH' => filesize(self::TEST_IMAGE),
            ],
            file_get_contents(self::TEST_IMAGE)
        );
        $response = $this->apiClient->getResponse();

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/media/' . $this->mediaId, $response->headers->get('Location'));

        static::assertTrue($this->filesystem->has('media/' . $path . '.png'));
    }

    public function testUploadFromURL(): void
    {
        $path = $this->strategy->encode($this->mediaId);
        static::assertFalse($this->filesystem->has('media/' . $path . '.jpg'));

        $url = getenv('APP_URL');

        $target = self::$container->getParameter('kernel.project_dir') . '/public/shopware-logo.png';
        copy(__DIR__ . '/../fixtures/shopware-logo.png', $target);

        $this->apiClient->request(
            'POST',
            "/api/v1/media/{$this->mediaId}/actions/upload",
            [],
            [],
            [
                'HTTP_CONTENT-TYPE' => 'application/json',
            ],
            json_encode(['url' => $url . '/shopware-logo.png'])
        );
        $response = $this->apiClient->getResponse();

        unlink($target);

        static::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        static::assertNotEmpty($response->headers->get('Location'));
        static::assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/media/' . $this->mediaId, $response->headers->get('Location'));
        static::assertTrue($this->filesystem->has('media/' . $path . '.png'));
    }
}

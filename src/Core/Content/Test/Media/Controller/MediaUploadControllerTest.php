<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Controller;

use League\Flysystem\FileNotFoundException;
use League\Flysystem\FilesystemInterface;
use Shopware\Core\Content\Media\Util\Strategy\StrategyInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\ORM\RepositoryInterface;
use Shopware\Core\Framework\ORM\Search\Criteria;
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

        $context = Context::createDefaultContext(Defaults::TENANT_ID);
        $criteria = new Criteria();
        $criteria->setLimit(1);
        $this->mediaId = $this->mediaRepository->searchIds($criteria, $context)->getIds()[0];
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
        $this->assertFalse($this->filesystem->has('media/' . $path . '.png'));

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

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $this->assertNotEmpty($response->headers->get('Location'));
        $this->assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/media/' . $this->mediaId, $response->headers->get('Location'));

        $this->assertTrue($this->filesystem->has('media/' . $path . '.png'));
    }

    public function testUploadFromURL(): void
    {
        $path = $this->strategy->encode($this->mediaId);
        $this->assertFalse($this->filesystem->has('media/' . $path . '.jpg'));

        $this->apiClient->request(
            'POST',
            "/api/v1/media/{$this->mediaId}/actions/upload",
            [],
            [],
            [
                'HTTP_CONTENT-TYPE' => 'application/json',
            ],
            json_encode(['url' => 'https://de.shopware.com/press/company/Shopware_Jamaica.jpg'])
        );
        $response = $this->apiClient->getResponse();

        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());

        $this->assertNotEmpty($response->headers->get('Location'));
        $this->assertEquals('http://localhost/api/v' . PlatformRequest::API_VERSION . '/media/' . $this->mediaId, $response->headers->get('Location'));

        $this->assertTrue($this->filesystem->has('media/' . $path . '.jpg'));
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\PlatformRequest;

class MediaFolderControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour, MediaFixtures;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaFolderRepo;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaFolderConfigRepo;

    protected function setUp(): void
    {
        $this->mediaFolderRepo = $this->getContainer()->get('media_folder.repository');
        $this->mediaFolderConfigRepo = $this->getContainer()->get('media_folder_configuration.repository');

        $this->context = Context::createDefaultContext();
    }

    public function testDissolveWithNonExistingFolder(): void
    {
        $url = sprintf(
            '/api/v%s/_action/media-folder/%s/dissolve',
            PlatformRequest::API_VERSION,
            Uuid::uuid4()->getHex()
        );

        $this->getClient()->request(
            'POST',
            $url
        );
        $response = $this->getClient()->getResponse();
        $responseData = json_decode($response->getContent(), true);

        static::assertEquals(404, $response->getStatusCode());
        static::assertEquals('MEDIA_FOLDER_NOT_FOUND_EXCEPTION', $responseData['errors'][0]['code']);
    }

    public function testDissolve(): void
    {
        $folderId = Uuid::uuid4()->getHex();
        $configId = Uuid::uuid4()->getHex();
        $this->mediaFolderRepo->create([
            [
                'id' => $folderId,
                'name' => 'test',
                'useParentConfiguration' => false,
                'configuration' => [
                    'id' => $configId,
                    'createThumbnails' => true,
                    'keepAspectRatio' => true,
                    'thumbnailQuality' => 80,
                ],
            ],
        ], $this->context);

        $url = sprintf(
            '/api/v%s/_action/media-folder/%s/dissolve',
            PlatformRequest::API_VERSION,
            $folderId
        );

        $this->getClient()->request(
            'POST',
            $url
        );
        $response = $this->getClient()->getResponse();

        static::assertEquals(200, $response->getStatusCode(), $response->getContent());
        static::assertEmpty($response->getContent());

        $folder = $this->mediaFolderRepo->search(new Criteria([$folderId]), $this->context)->get($folderId);
        static::assertNull($folder);

        $config = $this->mediaFolderConfigRepo->search(new Criteria([$configId]), $this->context)->get($configId);
        static::assertNull($config);
    }
}

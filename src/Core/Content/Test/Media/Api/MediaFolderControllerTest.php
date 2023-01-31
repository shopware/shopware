<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Api;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\AdminFunctionalTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MediaFolderControllerTest extends TestCase
{
    use AdminFunctionalTestBehaviour;
    use MediaFixtures;

    /**
     * @var EntityRepository
     */
    private $mediaFolderRepo;

    private Context $context;

    /**
     * @var EntityRepository
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
            '/api/_action/media-folder/%s/dissolve',
            Uuid::randomHex()
        );

        $this->getBrowser()->request(
            'POST',
            $url
        );
        $response = $this->getBrowser()->getResponse();
        $responseData = json_decode((string) $response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        static::assertEquals(404, $response->getStatusCode());
        static::assertEquals('CONTENT__MEDIA_FOLDER_NOT_FOUND', $responseData['errors'][0]['code']);
    }

    public function testDissolve(): void
    {
        $folderId = Uuid::randomHex();
        $configId = Uuid::randomHex();
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
            '/api/_action/media-folder/%s/dissolve',
            $folderId
        );

        $this->getBrowser()->request(
            'POST',
            $url
        );
        $response = $this->getBrowser()->getResponse();

        static::assertEquals(204, $response->getStatusCode(), (string) $response->getContent());
        static::assertEmpty($response->getContent());

        $folder = $this->mediaFolderRepo->search(new Criteria([$folderId]), $this->context)->get($folderId);
        static::assertNull($folder);

        $config = $this->mediaFolderConfigRepo->search(new Criteria([$configId]), $this->context)->get($configId);
        static::assertNull($config);
    }
}

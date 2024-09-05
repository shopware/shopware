<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Aggregate\MediaFolder;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MediaFolderTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private EntityRepository $mediaFolderRepository;

    protected function setUp(): void
    {
        $this->mediaFolderRepository = $this->getContainer()->get('media_folder.repository');
    }

    public function testCreateMediaFolderWithConfiguration(): void
    {
        $context = Context::createDefaultContext();

        $folderId = Uuid::randomHex();
        $configurationId = Uuid::randomHex();

        $this->mediaFolderRepository->upsert([
            [
                'id' => $folderId,
                'name' => 'default folder',
                'configuration' => [
                    'id' => $configurationId,
                    'createThumbnails' => true,
                ],
            ],
        ], $context);

        $criteria = new Criteria();
        $criteria->addAssociation('configuration');

        $collection = $this->mediaFolderRepository->search($criteria, $context)->getEntities();

        $mediaFolder = $collection->get($folderId);

        static::assertInstanceOf(MediaFolderEntity::class, $mediaFolder);
        static::assertEquals('default folder', $mediaFolder->getName());
        static::assertNotNull($mediaFolder->getConfigurationId());
        static::assertNotNull($mediaFolder->getConfiguration());
        static::assertTrue($mediaFolder->getConfiguration()->getCreateThumbnails());
    }

    public function testCreatedMediaFolderIsSetInConfiguration(): void
    {
        $context = Context::createDefaultContext();

        $folderId = Uuid::randomHex();
        $configurationId = Uuid::randomHex();

        $this->mediaFolderRepository->upsert([
            [
                'id' => $folderId,
                'name' => 'default folder',
                'configuration' => [
                    'id' => $configurationId,
                    'createThumbnails' => true,
                ],
            ],
        ], $context);

        $criteria = new Criteria();
        $criteria->addAssociation('mediaFolders');

        $mediaFolderConfigurationRepository = $this->getContainer()->get('media_folder_configuration.repository');
        $collection = $mediaFolderConfigurationRepository->search($criteria, $context)->getEntities();

        $configuration = $collection->get($configurationId);
        static::assertInstanceOf(MediaFolderConfigurationEntity::class, $configuration);
        static::assertInstanceOf(MediaFolderCollection::class, $configuration->getMediaFolders());
        static::assertNotNull($configuration->getMediaFolders()->get($folderId));
    }
}

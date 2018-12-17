<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Aggregate\MediaFolder;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MediaFolderTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testCreateMediaFolderWithConfiguration()
    {
        $context = Context::createDefaultContext();
        $mediaFolderRepository = $this->getContainer()->get('media_folder.repository');

        $folderId = Uuid::uuid4()->getHex();
        $configurationId = Uuid::uuid4()->getHex();

        $mediaFolderRepository->upsert([
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
        $criteria->addAssociation('mediaFolderConfiguration');

        $collection = $mediaFolderRepository->search($criteria, $context)->getEntities();

        /** @var MediaFolderEntity $mediaFolder */
        $mediaFolder = $collection->get($folderId);

        static::assertEquals('default folder', $mediaFolder->getName());
        static::assertNotNull($mediaFolder->getConfigurationId());
        static::assertNotNull($mediaFolder->getConfiguration());
        static::assertTrue($mediaFolder->getConfiguration()->getCreateThumbnails());
    }

    public function testCreatedMediaFolderIsSetInConfiguration()
    {
        $context = Context::createDefaultContext();
        $mediaFolderRepository = $this->getContainer()->get('media_folder.repository');

        $folderId = Uuid::uuid4()->getHex();
        $configurationId = Uuid::uuid4()->getHex();

        $mediaFolderRepository->upsert([
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
        static::assertNotNull($configuration->getMediaFolders()->get($folderId));
    }
}

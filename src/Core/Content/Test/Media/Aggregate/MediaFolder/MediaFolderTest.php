<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\Aggregate\MediaFolder;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderStruct;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
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

        /** @var MediaFolderStruct $mediaFolder */
        $mediaFolder = $collection->get($folderId);

        static::assertEquals('default folder', $mediaFolder->getName());
        static::assertNotNull($mediaFolder->getMediaFolderConfigurationId());
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

    public function testCreateMediaFolderTakesParentConfiguration()
    {
        $context = Context::createDefaultContext();
        $mediaFolderRepository = $this->getContainer()->get('media_folder.repository');

        $parentId = Uuid::uuid4()->getHex();
        $childId = Uuid::uuid4()->getHex();
        $configurationId = Uuid::uuid4()->getHex();

        $mediaFolderRepository->upsert([
            [
                'id' => $parentId,
                'name' => 'default folder',
                'configuration' => [
                    'id' => $configurationId,
                    'createThumbnails' => true,
                ],
            ],
        ], $context);

        $mediaFolderRepository->upsert([
            [
                'id' => $childId,
                'name' => 'child folder',
                'parentId' => $parentId,
            ],
        ], $context);

        $criteria = new Criteria();
        $criteria->addAssociation('mediaFolderConfiguration');
        $collection = $mediaFolderRepository->search($criteria, $context)->getEntities();

        /** @var MediaFolderStruct $mediaFolder */
        $mediaFolder = $collection->get($childId);

        static::assertEquals($parentId, $mediaFolder->getParentId());
        static::assertNotNull($mediaFolder->getConfiguration());
        static::assertEquals($configurationId, $mediaFolder->getConfiguration()->getId());
    }

    public function testFolderDeletesOverriddenConfiguration()
    {
        $context = Context::createDefaultContext();
        $mediaFolderRepository = $this->getContainer()->get('media_folder.repository');

        $parentId = Uuid::uuid4()->getHex();
        $childId = Uuid::uuid4()->getHex();
        $parentConfigurationId = Uuid::uuid4()->getHex();
        $childConfigurationId = Uuid::uuid4()->getHex();

        $mediaFolderRepository->upsert([
            [
                'name' => 'parent',
                'id' => $parentId,
                'configuration' => [
                    'id' => $parentConfigurationId,
                    'createThumbnails' => true,
                ],
            ],
            [
                'name' => 'child',
                'id' => $childId,
                'parentId' => $parentId,
                'configuration' => [
                    'id' => $childConfigurationId,
                    'createThumbnails' => false,
                ],
            ],
        ], $context);

        $entities = $mediaFolderRepository->read(new ReadCriteria([$parentId, $childId]), $context);

        /** @var MediaFolderStruct $parent */
        $parent = $entities->get($parentId);

        /** @var MediaFolderStruct $child */
        $child = $entities->get($childId);

        static::assertNotEquals($parent->getConfiguration()->getId(), $child->getConfiguration()->getId());

        $mediaFolderRepository->upsert([
            [
                'id' => $childId,
                'mediaFolderConfigurationId' => null,
            ],
        ], $context);

        $child = $mediaFolderRepository->read(new ReadCriteria([$childId]), $context)->get($childId);

        static::assertEquals($parent->getConfiguration()->getId(), $child->getConfiguration()->getId());
    }
}

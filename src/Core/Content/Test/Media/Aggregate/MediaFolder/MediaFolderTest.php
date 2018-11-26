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
        $repository = $this->getContainer()->get('media_folder.repository');

        $folderId = Uuid::uuid4()->getHex();
        $configurationId = Uuid::uuid4()->getHex();

        $repository->upsert([
            [
                'id' => $folderId,
                'name' => 'default folder',
                'configuration' => [
                    'id' => $configurationId,
                    'autoCreateThumbnails' => true,
                ],
            ],
        ], $context);

        $criteria = new Criteria();
        $criteria->addAssociation('mediaFolderConfiguration');

        $collection = $repository->search($criteria, $context)->getEntities();

        /** @var MediaFolderStruct $mediaFolder */
        $mediaFolder = $collection->get($folderId);

        static::assertEquals('default folder', $mediaFolder->getName());
        static::assertNotNull($mediaFolder->getMediaFolderConfigurationId());
        static::assertNotNull($mediaFolder->getConfiguration());
        static::assertTrue($mediaFolder->getConfiguration()->getAutoCreateThumbnails());
    }

    public function testCreateMediaFolderTakesParentConfiguration()
    {
        $context = Context::createDefaultContext();
        $repository = $this->getContainer()->get('media_folder.repository');

        $parentId = Uuid::uuid4()->getHex();
        $childId = Uuid::uuid4()->getHex();
        $configurationId = Uuid::uuid4()->getHex();

        $repository->upsert([
            [
                'id' => $parentId,
                'name' => 'default folder',
                'configuration' => [
                    'id' => $configurationId,
                    'autoCreateThumbnails' => true,
                ],
            ],
        ], $context);

        $repository->upsert([
            [
                'id' => $childId,
                'name' => 'child folder',
                'parentId' => $parentId,
            ],
        ], $context);

        $criteria = new Criteria();
        $criteria->addAssociation('mediaFolderConfiguration');
        $collection = $repository->search($criteria, $context)->getEntities();

        /** @var MediaFolderStruct $mediaFolder */
        $mediaFolder = $collection->get($childId);

        static::assertEquals($parentId, $mediaFolder->getParentId());
        static::assertNotNull($mediaFolder->getConfiguration());
        static::assertEquals($configurationId, $mediaFolder->getConfiguration()->getId());
    }

    public function testFolderDeletesOverriddenConfiguration()
    {
        $context = Context::createDefaultContext();
        $repository = $this->getContainer()->get('media_folder.repository');

        $parentId = Uuid::uuid4()->getHex();
        $childId = Uuid::uuid4()->getHex();
        $parentConfigurationId = Uuid::uuid4()->getHex();
        $childConfigurationId = Uuid::uuid4()->getHex();

        $repository->upsert([
            [
                'name' => 'parent',
                'id' => $parentId,
                'configuration' => [
                    'id' => $parentConfigurationId,
                    'autoCreateThumbnails' => true,
                ],
            ],
            [
                'name' => 'child',
                'id' => $childId,
                'parentId' => $parentId,
                'configuration' => [
                    'id' => $childConfigurationId,
                    'autoCreateThumbnails' => false,
                ],
            ],
        ], $context);

        $entities = $repository->read(new ReadCriteria([$parentId, $childId]), $context);

        /** @var MediaFolderStruct $parent */
        $parent = $entities->get($parentId);

        /** @var MediaFolderStruct $child */
        $child = $entities->get($childId);

        static::assertNotEquals($parent->getConfiguration()->getId(), $child->getConfiguration()->getId());

        $repository->upsert([
            [
                'id' => $childId,
                'mediaFolderConfigurationId' => null,
            ],
        ], $context);

        $child = $repository->read(new ReadCriteria([$childId]), $context)->get($childId);

        static::assertEquals($parent->getConfiguration()->getId(), $child->getConfiguration()->getId());
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Exception\MediaFolderNotFoundException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaFolderService;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MediaFolderServiceTest extends TestCase
{
    use IntegrationTestBehaviour, MediaFixtures;

    /**
     * @var MediaFolderService
     */
    private $mediaFolderService;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaFolderRepo;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaFolderConfigRepo;

    /**
     * @var Context
     */
    private $context;

    public function setUp()
    {
        $this->mediaRepo = $this->getContainer()->get('media.repository');
        $this->mediaFolderRepo = $this->getContainer()->get('media_folder.repository');
        $this->mediaFolderConfigRepo = $this->getContainer()->get('media_folder_configuration.repository');

        $this->context = Context::createDefaultContext();
        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);

        $this->mediaFolderService = $this->getContainer()->get(MediaFolderService::class);
    }

    public function testDissolveForNonExistingFolder()
    {
        static::expectException(MediaFolderNotFoundException::class);

        $this->mediaFolderService->dissolve(Uuid::uuid4()->getHex(), $this->context);
    }

    public function testDissolveWithNoChildFolders()
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();

        $configId = $this->mediaFolderRepo
            ->read(new ReadCriteria([$media->getMediaFolderId()]), $this->context)
            ->get($media->getMediaFolderId())
            ->getConfigurationId();

        $this->mediaFolderService->dissolve($media->getMediaFolderId(), $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasNoFolder($media);
        $this->assertConfigIsDeleted($configId);
    }

    public function testDissolveChildToRootLevel()
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();
        $childId = Uuid::uuid4()->getHex();
        $configId = Uuid::uuid4()->getHex();

        $this->mediaFolderRepo->create([
            [
                'id' => $childId,
                'name' => 'child',
                'parentId' => $media->getMediaFolderId(),
                'useParentConfiguration' => false,
                'configuration' => [
                    'id' => $configId,
                    'createThumbnails' => true,
                    'keepAspectRatio' => true,
                    'thumbnailQuality' => 80,
                ],
            ],
        ], $this->context);

        $this->mediaFolderService->dissolve($media->getMediaFolderId(), $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasNoFolder($media);
        $this->assertMediaFolderIsAtRootLevel($childId);
        $this->assertConfigStillExists($configId);
    }

    public function testDissolveWithInheritedConfig()
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();
        $parentId = Uuid::uuid4()->getHex();
        $configId = Uuid::uuid4()->getHex();

        $this->mediaFolderRepo->create([
            [
                'id' => $parentId,
                'name' => 'parent',
                'useParentConfiguration' => false,
                'configuration' => [
                    'id' => $configId,
                    'createThumbnails' => true,
                    'keepAspectRatio' => true,
                    'thumbnailQuality' => 80,
                ],
            ],
        ], $this->context);

        $this->mediaFolderRepo->update([
            [
                'id' => $media->getMediaFolderId(),
                'parentId' => $parentId,
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
        ], $this->context);

        $this->mediaFolderService->dissolve($media->getMediaFolderId(), $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasParentFolder($media, $parentId);
        $this->assertConfigStillExists($configId);
    }

    public function testDissolveWithChildren()
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();
        $configId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();
        $child1Id = Uuid::uuid4()->getHex();
        $child2Id = Uuid::uuid4()->getHex();
        $child3Id = Uuid::uuid4()->getHex();
        $childConfigId = Uuid::uuid4()->getHex();

        $this->mediaFolderRepo->create([
            [
                'id' => $parentId,
                'name' => 'parent',
                'useParentConfiguration' => false,
                'configuration' => [
                    'createThumbnails' => true,
                    'keepAspectRatio' => true,
                    'thumbnailQuality' => 80,
                ],
            ],
            [
                'id' => $child1Id,
                'name' => 'child 1',
                'parentId' => $media->getMediaFolderId(),
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
            [
                'id' => $child2Id,
                'name' => 'child 2',
                'parentId' => $media->getMediaFolderId(),
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
            [
                'id' => $child3Id,
                'name' => 'child 3',
                'parentId' => $media->getMediaFolderId(),
                'useParentConfiguration' => false,
                'configuration' => [
                    'id' => $childConfigId,
                    'createThumbnails' => true,
                    'keepAspectRatio' => true,
                    'thumbnailQuality' => 80,
                ],
            ],
        ], $this->context);

        $this->mediaFolderRepo->update([
            [
                'id' => $media->getMediaFolderId(),
                'parentId' => $parentId,
                'configuration' => [
                    'id' => $configId,
                ],
            ],
        ], $this->context);

        $this->mediaFolderService->dissolve($media->getMediaFolderId(), $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasParentFolder($media, $parentId);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('parentId', $parentId));
        $folders = $this->mediaFolderRepo
            ->search($criteria, $this->context)
            ->getEntities();

        $this->assertConfig($folders->get($child1Id), false, true, true, 80);
        $this->assertConfig($folders->get($child2Id), false, true, true, 80);

        static::assertNotEquals($configId === $folders->get($child1Id)->getConfigurationId(), $configId === $folders->get($child2Id)->getConfigurationId());

        static::assertNotNull($folders->get($child3Id));
        static::assertEquals($childConfigId, $folders->get($child3Id)->getConfigurationId());
    }

    public function testDissolveWithMultipleLayerOfChildren()
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();
        $configId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();
        $child1Id = Uuid::uuid4()->getHex();
        $child1_1Id = Uuid::uuid4()->getHex();
        $child1_1_1Id = Uuid::uuid4()->getHex();
        $child2Id = Uuid::uuid4()->getHex();
        $child2_1Id = Uuid::uuid4()->getHex();
        $child2_1_1Id = Uuid::uuid4()->getHex();

        $this->mediaFolderRepo->create([
            [
                'id' => $parentId,
                'name' => 'parent',
                'useParentConfiguration' => false,
                'configuration' => [
                    'createThumbnails' => true,
                    'keepAspectRatio' => true,
                    'thumbnailQuality' => 80,
                ],
            ],
            [
                'id' => $child1Id,
                'name' => 'child 1',
                'parentId' => $media->getMediaFolderId(),
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
            [
                'id' => $child1_1Id,
                'name' => 'child 1.1',
                'parentId' => $child1Id,
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
            [
                'id' => $child1_1_1Id,
                'name' => 'child 1.1.1',
                'parentId' => $child1_1Id,
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
            [
                'id' => $child2Id,
                'name' => 'child 2',
                'parentId' => $media->getMediaFolderId(),
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
            [
                'id' => $child2_1Id,
                'name' => 'child 2.1',
                'parentId' => $child2Id,
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
            [
                'id' => $child2_1_1Id,
                'name' => 'child 2.1.1',
                'parentId' => $child2_1Id,
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
        ], $this->context);

        $this->mediaFolderRepo->update([
            [
                'id' => $media->getMediaFolderId(),
                'parentId' => $parentId,
                'configuration' => [
                    'id' => $configId,
                ],
            ],
        ], $this->context);

        $this->mediaFolderService->dissolve($media->getMediaFolderId(), $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasParentFolder($media, $parentId);

        $folders = $this->mediaFolderRepo
            ->search(new Criteria(), $this->context)
            ->getEntities();

        static::assertNotEquals($configId === $folders->get($child1Id)->getConfigurationId(), $configId === $folders->get($child2Id)->getConfigurationId());

        $this->assertConfigIsSame($folders->get($child1Id), $folders->get($child1_1Id));
        $this->assertConfigIsSame($folders->get($child1Id), $folders->get($child1_1_1Id));

        $this->assertConfigIsSame($folders->get($child2Id), $folders->get($child2_1Id));
        $this->assertConfigIsSame($folders->get($child2Id), $folders->get($child2_1_1Id));
    }

    public function testDissolveWithInheritedConfigAndChildren()
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();
        $configId = Uuid::uuid4()->getHex();
        $parentId = Uuid::uuid4()->getHex();
        $child1Id = Uuid::uuid4()->getHex();
        $child2Id = Uuid::uuid4()->getHex();

        $this->mediaFolderRepo->create([
            [
                'id' => $parentId,
                'name' => 'parent',
                'useParentConfiguration' => false,
                'configuration' => [
                    'id' => $configId,
                    'createThumbnails' => true,
                    'keepAspectRatio' => true,
                    'thumbnailQuality' => 80,
                ],
            ],
            [
                'id' => $child1Id,
                'name' => 'child 1',
                'parentId' => $media->getMediaFolderId(),
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
            [
                'id' => $child2Id,
                'name' => 'child 2',
                'parentId' => $media->getMediaFolderId(),
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
        ], $this->context);

        $this->mediaFolderRepo->update([
            [
                'id' => $media->getMediaFolderId(),
                'parentId' => $parentId,
                'useParentConfiguration' => true,
                'configurationId' => $configId,
            ],
        ], $this->context);

        $this->mediaFolderService->dissolve($media->getMediaFolderId(), $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasParentFolder($media, $parentId);

        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('parentId', $parentId));
        $folders = $this->mediaFolderRepo
            ->search($criteria, $this->context)
            ->getEntities();
        static::assertNotNull($folders->get($child1Id));
        static::assertNotNull($folders->get($child2Id));
    }

    private function assertMediaFolderIsAtRootLevel(string $folderId): void
    {
        $folder = $this->mediaFolderRepo
            ->read(new ReadCriteria([$folderId]), $this->context)
            ->get($folderId);
        static::assertNull($folder->getParentId());
    }

    private function assertMediaFolderIsDeleted(MediaEntity $media): void
    {
        $folder = $this->mediaFolderRepo
            ->read(new ReadCriteria([$media->getMediaFolderId()]), $this->context)
            ->get($media->getMediaFolderId());
        static::assertNull($folder);
    }

    private function assertMediaHasNoFolder(MediaEntity $media): void
    {
        $media = $this->mediaRepo->read(new ReadCriteria([$media->getId()]), $this->context)->get($media->getId());
        static::assertNull($media->getMediaFolderId());
    }

    private function assertMediaHasParentFolder(MediaEntity $media, string $parentId): void
    {
        $media = $this->mediaRepo->read(new ReadCriteria([$media->getId()]), $this->context)->get($media->getId());
        static::assertEquals($parentId, $media->getMediaFolderId());
    }

    private function assertConfigIsDeleted($configId): void
    {
        $config = $this->mediaFolderConfigRepo->read(new ReadCriteria([$configId]), $this->context)->get($configId);
        static::assertNull($config);
    }

    private function assertConfigStillExists(string $configId): void
    {
        $config = $this->mediaFolderConfigRepo->read(new ReadCriteria([$configId]), $this->context)->get($configId);
        static::assertNotNull($config);
    }

    private function assertConfig(
        MediaFolderEntity $folder,
        bool $useParentConfiguration,
        bool $createThumbnails,
        bool $keepAspectRatio,
        int $thumbnailQuality
    ): void {
        static::assertNotNull($folder);
        static::assertEquals($useParentConfiguration, $folder->getUseParentConfiguration());
        static::assertEquals($createThumbnails, $folder->getConfiguration()->getCreateThumbnails());
        static::assertEquals($keepAspectRatio, $folder->getConfiguration()->getKeepAspectRatio());
        static::assertEquals($thumbnailQuality, $folder->getConfiguration()->getThumbnailQuality());
    }

    private function assertConfigIsSame(MediaFolderEntity $folder, MediaFolderEntity $childFolder): void
    {
        static::assertEquals($folder->getConfigurationId(), $childFolder->getConfigurationId());
    }

    private function assertConfigValuesEqual(
        MediaFolderConfigurationEntity $expected,
        MediaFolderConfigurationEntity $actual
    ): void {
        static::assertEquals($expected->getCreateThumbnails(), $actual->getCreateThumbnails());
        static::assertEquals($expected->getKeepAspectRatio(), $actual->getKeepAspectRatio());
        static::assertEquals($expected->getThumbnailQuality(), $actual->getThumbnailQuality());
    }
}

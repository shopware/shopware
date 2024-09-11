<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Content\Media\MediaFolderService;
use Shopware\Core\Content\Test\Media\MediaFixtures;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MediaFolderServiceTest extends TestCase
{
    use IntegrationTestBehaviour;
    use MediaFixtures;

    /**
     * @var MediaFolderService
     */
    private $mediaFolderService;

    /**
     * @var EntityRepository
     */
    private $mediaRepo;

    /**
     * @var EntityRepository
     */
    private $mediaFolderRepo;

    /**
     * @var EntityRepository
     */
    private $mediaFolderConfigRepo;

    private Context $context;

    protected function setUp(): void
    {
        $this->mediaRepo = $this->getContainer()->get('media.repository');
        $this->mediaFolderRepo = $this->getContainer()->get('media_folder.repository');
        $this->mediaFolderConfigRepo = $this->getContainer()->get('media_folder_configuration.repository');

        $this->context = Context::createDefaultContext();

        $this->mediaFolderService = $this->getContainer()->get(MediaFolderService::class);
    }

    public function testDissolveForNonExistingFolder(): void
    {
        $folderId = Uuid::randomHex();
        $this->expectException(MediaException::class);
        $this->expectExceptionMessage(MediaException::mediaFolderIdNotFound($folderId)->getMessage());

        $this->mediaFolderService->dissolve($folderId, $this->context);
    }

    public function testDissolveWithNoChildFolders(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();

        $mediaFolderId = $media->getMediaFolderId();
        static::assertIsString($mediaFolderId);

        $mediaFolder = $this->mediaFolderRepo
            ->search(new Criteria(array_filter([$mediaFolderId])), $this->context)
            ->get($mediaFolderId);
        static::assertInstanceOf(MediaFolderEntity::class, $mediaFolder);

        $configId = $mediaFolder->getConfigurationId();

        $this->mediaFolderService->dissolve($mediaFolderId, $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasNoFolder($media);
        static::assertIsString($configId);
        $this->assertConfigIsDeleted($configId);
    }

    public function testDissolveChildToRootLevel(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();
        $childId = Uuid::randomHex();
        $configId = Uuid::randomHex();

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

        $mediaFolderId = $media->getMediaFolderId();
        static::assertIsString($mediaFolderId);
        $this->mediaFolderService->dissolve($mediaFolderId, $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasNoFolder($media);
        $this->assertMediaFolderIsAtRootLevel($childId);
        $this->assertConfigStillExists($configId);
    }

    public function testDissolveWithInheritedConfig(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();
        $parentId = Uuid::randomHex();
        $configId = Uuid::randomHex();

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

        $mediaFolderId = $media->getMediaFolderId();
        static::assertIsString($mediaFolderId);
        $this->mediaFolderService->dissolve($mediaFolderId, $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasParentFolder($media, $parentId);
        $this->assertConfigStillExists($configId);
    }

    public function testDissolveWithChildren(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();
        $configId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $child1Id = Uuid::randomHex();
        $child2Id = Uuid::randomHex();
        $child3Id = Uuid::randomHex();
        $childConfigId = Uuid::randomHex();

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

        $mediaFolderId = $media->getMediaFolderId();
        static::assertIsString($mediaFolderId);
        $this->mediaFolderService->dissolve($mediaFolderId, $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasParentFolder($media, $parentId);

        $criteria = (new Criteria())
            ->addAssociation('configuration')
            ->addFilter(new EqualsFilter('parentId', $parentId));

        $folders = $this->mediaFolderRepo
            ->search($criteria, $this->context)
            ->getEntities();
        static::assertInstanceOf(MediaFolderCollection::class, $folders);

        $foldersChild1 = $folders->get($child1Id);
        static::assertInstanceOf(MediaFolderEntity::class, $foldersChild1);

        $foldersChild2 = $folders->get($child2Id);
        static::assertInstanceOf(MediaFolderEntity::class, $foldersChild2);

        $foldersChild3 = $folders->get($child3Id);
        static::assertInstanceOf(MediaFolderEntity::class, $foldersChild3);

        $this->assertConfig($foldersChild1, false, true, true, 80);
        $this->assertConfig($foldersChild2, false, true, true, 80);

        static::assertNotEquals($configId === $foldersChild1->getConfigurationId(), $configId === $foldersChild2->getConfigurationId());
        static::assertEquals($childConfigId, $foldersChild3->getConfigurationId());
    }

    public function testDissolveWithMultipleLayerOfChildren(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();
        $configId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $child1Id = Uuid::randomHex();
        $child1_1Id = Uuid::randomHex();
        $child1_1_1Id = Uuid::randomHex();
        $child2Id = Uuid::randomHex();
        $child2_1Id = Uuid::randomHex();
        $child2_1_1Id = Uuid::randomHex();

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

        $mediaFolderId = $media->getMediaFolderId();
        static::assertIsString($mediaFolderId);
        $this->mediaFolderService->dissolve($mediaFolderId, $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasParentFolder($media, $parentId);

        $folders = $this->mediaFolderRepo
            ->search(new Criteria(), $this->context)
            ->getEntities();
        static::assertInstanceOf(MediaFolderCollection::class, $folders);

        $foldersChild1 = $folders->get($child1Id);
        static::assertInstanceOf(MediaFolderEntity::class, $foldersChild1);

        $foldersChild2 = $folders->get($child2Id);
        static::assertInstanceOf(MediaFolderEntity::class, $foldersChild2);

        static::assertNotEquals($configId === $foldersChild1->getConfigurationId(), $configId === $foldersChild2->getConfigurationId());

        $foldersChild1_1Id = $folders->get($child1_1Id);
        static::assertInstanceOf(MediaFolderEntity::class, $foldersChild1_1Id);

        $foldersChild1_1_1Id = $folders->get($child1_1_1Id);
        static::assertInstanceOf(MediaFolderEntity::class, $foldersChild1_1_1Id);

        $foldersChild2_1Id = $folders->get($child2_1Id);
        static::assertInstanceOf(MediaFolderEntity::class, $foldersChild2_1Id);

        $foldersChild2_1_1Id = $folders->get($child2_1_1Id);
        static::assertInstanceOf(MediaFolderEntity::class, $foldersChild2_1_1Id);

        $this->assertConfigIsSame($foldersChild1, $foldersChild1_1Id);
        $this->assertConfigIsSame($foldersChild1, $foldersChild1_1_1Id);

        $this->assertConfigIsSame($foldersChild2, $foldersChild2_1Id);
        $this->assertConfigIsSame($foldersChild2, $foldersChild2_1_1Id);
    }

    public function testDissolveWithInheritedConfigAndChildren(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();
        $configId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $child1Id = Uuid::randomHex();
        $child2Id = Uuid::randomHex();

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

        $mediaFolderId = $media->getMediaFolderId();
        static::assertIsString($mediaFolderId);
        $this->mediaFolderService->dissolve($mediaFolderId, $this->context);

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
            ->search(new Criteria([$folderId]), $this->context)
            ->get($folderId);
        static::assertInstanceOf(MediaFolderEntity::class, $folder);

        static::assertNull($folder->getParentId());
    }

    private function assertMediaFolderIsDeleted(MediaEntity $media): void
    {
        $mediaFolderId = $media->getMediaFolderId();
        static::assertIsString($mediaFolderId);
        $folder = $this->mediaFolderRepo
            ->search(new Criteria(array_filter([$mediaFolderId])), $this->context)
            ->get($mediaFolderId);
        static::assertNull($folder);
    }

    private function assertMediaHasNoFolder(MediaEntity $media): void
    {
        $media = $this->mediaRepo->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertNull($media->getMediaFolderId());
    }

    private function assertMediaHasParentFolder(MediaEntity $media, string $parentId): void
    {
        $media = $this->mediaRepo->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());

        static::assertInstanceOf(MediaEntity::class, $media);
        static::assertEquals($parentId, $media->getMediaFolderId());
    }

    private function assertConfigIsDeleted(string $configId): void
    {
        $config = $this->mediaFolderConfigRepo->search(new Criteria([$configId]), $this->context)->get($configId);
        static::assertNull($config);
    }

    private function assertConfigStillExists(string $configId): void
    {
        $config = $this->mediaFolderConfigRepo->search(new Criteria([$configId]), $this->context)->get($configId);
        static::assertNotNull($config);
    }

    private function assertConfig(
        MediaFolderEntity $folder,
        bool $useParentConfiguration,
        bool $createThumbnails,
        bool $keepAspectRatio,
        int $thumbnailQuality
    ): void {
        static::assertEquals($useParentConfiguration, $folder->getUseParentConfiguration());
        static::assertEquals($createThumbnails, $folder->getConfiguration()?->getCreateThumbnails());
        static::assertEquals($keepAspectRatio, $folder->getConfiguration()?->getKeepAspectRatio());
        static::assertEquals($thumbnailQuality, $folder->getConfiguration()?->getThumbnailQuality());
    }

    private function assertConfigIsSame(MediaFolderEntity $folder, MediaFolderEntity $childFolder): void
    {
        static::assertEquals($folder->getConfigurationId(), $childFolder->getConfigurationId());
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Exception\MediaFolderNotFoundException;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Media\MediaFolderService;
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
        $this->expectException(MediaFolderNotFoundException::class);

        $this->mediaFolderService->dissolve(Uuid::randomHex(), $this->context);
    }

    public function testDissolveWithNoChildFolders(): void
    {
        $this->setFixtureContext($this->context);
        $media = $this->getJpgWithFolder();

        $mediaFolderId = $media->getMediaFolderId();
        static::assertIsString($mediaFolderId);
        $configId = $this->mediaFolderRepo
            ->search(new Criteria(array_filter([$mediaFolderId])), $this->context)
            ->get($mediaFolderId)
            ->getConfigurationId();

        $this->mediaFolderService->dissolve($mediaFolderId, $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasNoFolder($media);
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

        $this->mediaFolderService->dissolve($media->getMediaFolderId(), $this->context);

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

        $this->mediaFolderService->dissolve($media->getMediaFolderId(), $this->context);

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

        $this->mediaFolderService->dissolve($media->getMediaFolderId(), $this->context);

        $this->assertMediaFolderIsDeleted($media);
        $this->assertMediaHasParentFolder($media, $parentId);

        $criteria = (new Criteria())
            ->addAssociation('configuration')
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
            ->search(new Criteria([$folderId]), $this->context)
            ->get($folderId);
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
        static::assertNull($media->getMediaFolderId());
    }

    private function assertMediaHasParentFolder(MediaEntity $media, string $parentId): void
    {
        $media = $this->mediaRepo->search(new Criteria([$media->getId()]), $this->context)->get($media->getId());
        static::assertEquals($parentId, $media->getMediaFolderId());
    }

    private function assertConfigIsDeleted($configId): void
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
}

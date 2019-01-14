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
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MediaFolderServiceTest extends TestCase
{
    use IntegrationTestBehaviour, MediaFixtures, MediaFolderFixtures;

    /**
     * @var MediaFolderService
     */
    private $mediaFolderService;

    /**
     * @var RepositoryInterface
     */
    private $mediaRepo;

    /**
     * @var RepositoryInterface
     */
    private $mediaFolderRepo;

    /**
     * @var RepositoryInterface
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

    public function testMoveANonExistingFolder()
    {
        static::expectException(MediaFolderNotFoundException::class);

        $targetFolderId = $this->newEmptyFolder();

        $this->mediaFolderService->move(Uuid::uuid4()->getHex(), $targetFolderId, $this->context);
    }

    public function testMoveToANonExistingFolder()
    {
        static::expectException(MediaFolderNotFoundException::class);

        $folderToMoveId = $this->newEmptyFolder();

        $this->mediaFolderService->move($folderToMoveId, Uuid::uuid4()->getHex(), $this->context);
    }

    public function testMoveMediaFolderWithoutInheritance()
    {
        $folderToMoveId = $this->newEmptyFolder();
        $targetFolderId = $this->newEmptyFolder();

        $this->mediaFolderService->move($folderToMoveId, $targetFolderId, $this->context);

        $movedFolder = $this->getSingleFolderFromRepo($folderToMoveId);

        static::assertEquals($targetFolderId, $movedFolder->getParentId());
    }

    public function testMoveMediaFolderToRoot()
    {
        $parentFolderId = Uuid::uuid4()->getHex();
        $folderToMoveId = Uuid::uuid4()->getHex();

        $fixtures = $this->genFolders(
            [
                'id' => $parentFolderId,
                'configuration' => $this->genMediaFolderConfig(
                    [
                        'thumbnailQuality' => 79,
                        'createThumbnails' => true,
                        'keepAspectRatio' => false,
                    ]
                ),
            ],
            $this->genFolders(
                [
                    'id' => $folderToMoveId,
                    'useParentConfiguration' => true,
                ]
            )
        );

        $this->mediaFolderRepo->create($fixtures, $this->context);

        $preMoveConfig = $this->getSingleFolderFromRepo($parentFolderId)
            ->getConfiguration();

        $this->mediaFolderService->move($folderToMoveId, null, $this->context);

        /** @var MediaFolderEntity $movedFolder */
        $movedFolder = $this->getSingleFolderFromRepo($folderToMoveId);
        static::assertNull($movedFolder->getParentId());

        $movedFolderConfig = $movedFolder->getConfiguration();

        static::assertFalse($movedFolder->getUseParentConfiguration());

        static::assertNotEquals($preMoveConfig->getId(), $movedFolderConfig->getId());
        $this->assertConfigValuesEqual($preMoveConfig, $movedFolderConfig);
    }

    public function testMoveMediaFolderWithInheritance()
    {
        $parentFolderId = Uuid::uuid4()->getHex();
        $folderToMoveId = Uuid::uuid4()->getHex();
        $targetFolderId = Uuid::uuid4()->getHex();

        $fixtures = $this->genFolders(
            [
                'id' => $parentFolderId,
                'configuration' => $this->genMediaFolderConfig(
                    [
                        'thumbnailQuality' => 79,
                        'createThumbnails' => true,
                        'keepAspectRatio' => false,
                    ]
                ),
            ],
            $this->genFolders(
                [
                    'id' => $folderToMoveId,
                    'useParentConfiguration' => true,
                ]
            )
        );

        $fixtures[] = $this->genFolders(['id' => $targetFolderId])[0];
        $this->mediaFolderRepo->create($fixtures, $this->context);

        $preMoveConfig = $this->getSingleFolderFromRepo($parentFolderId)
            ->getConfiguration();

        $this->mediaFolderService->move($folderToMoveId, $targetFolderId, $this->context);

        /** @var MediaFolderEntity $movedFolder */
        $movedFolder = $this->getSingleFolderFromRepo($folderToMoveId);
        static::assertEquals($targetFolderId, $movedFolder->getParentId());

        $movedFolderConfig = $movedFolder->getConfiguration();

        static::assertFalse($movedFolder->getUseParentConfiguration());

        static::assertNotEquals($preMoveConfig->getId(), $movedFolderConfig->getId());
        $this->assertConfigValuesEqual($preMoveConfig, $movedFolderConfig);
    }

    public function testMoveUpdatesSubFolderConfigs()
    {
        $folderToMoveId = Uuid::uuid4()->getHex();
        $subFolderWithInheritanceId = Uuid::uuid4()->getHex();
        $subSubFolderWithInheritanceId = Uuid::uuid4()->getHex();
        $subFolderWithoutInheritanceId = Uuid::uuid4()->getHex();
        $subFolderIds = [$subFolderWithInheritanceId, $subFolderWithoutInheritanceId, $subSubFolderWithInheritanceId];
        $nonInheritedConfigId = Uuid::uuid4()->getHex();

        $fixtures = $this->genFolders(
            ['name' => 'preMoveParent'],
            $this->genFolders(
                [
                    'id' => $folderToMoveId,
                    'useParentConfiguration' => true,
                ],
                $this->genFolders(
                    [
                        'id' => $subFolderWithoutInheritanceId,
                        'configuration' => $this->genMediaFolderConfig(['id' => $nonInheritedConfigId]),
                    ],
                    $this->genFolders()
                ),
                $this->genFolders(
                    [
                        'id' => $subFolderWithInheritanceId,
                        'useParentConfiguration' => true,
                    ],
                    $this->genFolders(
                        [
                            'id' => $subSubFolderWithInheritanceId,
                            'useParentConfiguration' => true,
                        ]
                    )
                )
            )
        );

        $targetFolderId = Uuid::uuid4()->getHex();
        $fixtures[] = $this->genFolders(['id' => $targetFolderId])[0];

        $this->mediaFolderRepo->create($fixtures, $this->context);

        $this->mediaFolderService->move($folderToMoveId, $targetFolderId, $this->context);

        $postMoveSubFolders = $this->getCollectionFromRepo($subFolderIds);

        $postMoveParent = $this->getSingleFolderFromRepo($folderToMoveId);
        static::assertEquals($targetFolderId, $postMoveParent->getParentId());

        $postMoveParentConfig = $postMoveParent->getConfiguration();

        $postMoveUnInheritedConfig = $postMoveSubFolders
            ->get($subFolderWithoutInheritanceId)
            ->getConfiguration();

        $postMoveInheritedConfig = $postMoveSubFolders
            ->get($subFolderWithInheritanceId)
            ->getConfiguration();

        static::assertEquals(
            $nonInheritedConfigId,
            $postMoveUnInheritedConfig->getId()
        );

        static::assertEquals(
            $postMoveParentConfig->getId(),
            $postMoveInheritedConfig->getId()
        );

        static::assertEquals(
            $postMoveParentConfig->getId(),
            $postMoveSubFolders->get($subSubFolderWithInheritanceId)->getConfigurationId()
        );
    }

    private function newEmptyFolder(): string
    {
        $newFolderId = Uuid::uuid4()->getHex();

        $this->mediaFolderRepo->create($this->genFolders(['id' => $newFolderId]), $this->context);

        return $newFolderId;
    }

    private function getSingleFolderFromRepo(string $id): MediaFolderEntity
    {
        return $this->mediaFolderRepo
            ->read(new ReadCriteria([$id]), $this->context)
            ->get($id);
    }

    private function getCollectionFromRepo(array $subFolderIds): EntityCollection
    {
        $postMoveSubFolders = $this->mediaFolderRepo->read(new ReadCriteria($subFolderIds), $this->context);

        return $postMoveSubFolders;
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

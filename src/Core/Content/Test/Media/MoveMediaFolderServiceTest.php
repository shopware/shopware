<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration\MediaFolderConfigurationEntity;
use Shopware\Core\Content\Media\Exception\MediaFolderNotFoundException;
use Shopware\Core\Content\Media\MediaProtectionFlags;
use Shopware\Core\Content\Media\MoveMediaFolderService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Read\ReadCriteria;
use Shopware\Core\Framework\DataAbstractionLayer\RepositoryInterface;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class MoveMediaFolderServiceTest extends TestCase
{
    use IntegrationTestBehaviour, MediaFolderFixtures;

    /**
     * @var MoveMediaFolderService
     */
    private $moveMediaFolderService;

    /**
     * @var Context
     */
    private $context;
    /**
     * @var RepositoryInterface
     */
    private $mediaFolderRepo;

    public function setUp()
    {
        $this->context = Context::createDefaultContext();
        $this->context->getWriteProtection()->allow(MediaProtectionFlags::WRITE_META_INFO);

        $this->mediaFolderRepo = $this->getContainer()->get('media_folder.repository');
        $mediaFolderConfigRepo = $this->getContainer()->get('media_folder_configuration.repository');
        $this->moveMediaFolderService = new MoveMediaFolderService($this->mediaFolderRepo, $mediaFolderConfigRepo);

        // TODO Get service from the container once the folder controller is merged
        // $this->moveMediaFolderService = $this->getContainer()->get(MoveMediaFolderService::class);
    }

    public function testMoveANonExistingFolder()
    {
        static::expectException(MediaFolderNotFoundException::class);

        $targetFolderId = $this->newEmptyFolder();

        $this->moveMediaFolderService->move(Uuid::uuid4()->getHex(), $targetFolderId, $this->context);
    }

    public function testMoveToANonExistingFolder()
    {
        static::expectException(MediaFolderNotFoundException::class);

        $folderToMoveId = $this->newEmptyFolder();

        $this->moveMediaFolderService->move($folderToMoveId, Uuid::uuid4()->getHex(), $this->context);
    }

    public function testMoveMediaFolderWithoutInheritance()
    {
        $folderToMoveId = $this->newEmptyFolder();
        $targetFolderId = $this->newEmptyFolder();

        $this->moveMediaFolderService->move($folderToMoveId, $targetFolderId, $this->context);

        $movedFolder = $this->getSingleFolderFromRepo($folderToMoveId);

        static::assertEquals($targetFolderId, $movedFolder->getParentId());
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

        $this->moveMediaFolderService->move($folderToMoveId, $targetFolderId, $this->context);

        /** @var MediaFolderEntity $movedFolder */
        $movedFolder = $this->getSingleFolderFromRepo($folderToMoveId);

        $movedFolderConfig = $movedFolder->getConfiguration();

        static::assertFalse($movedFolder->isUseParentConfiguration());

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

        $this->moveMediaFolderService->move($folderToMoveId, $targetFolderId, $this->context);

        $postMoveSubFolders = $this->getCollectionFromRepo($subFolderIds);

        $postMoveParentConfig = $this->getSingleFolderFromRepo($folderToMoveId)
            ->getConfiguration();

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

    private function assertConfigValuesEqual(
        MediaFolderConfigurationEntity $expected,
        MediaFolderConfigurationEntity $actual
    ): void {
        static::assertEquals($expected->getCreateThumbnails(), $actual->getCreateThumbnails());
        static::assertEquals($expected->getKeepAspectRatio(), $actual->getKeepAspectRatio());
        static::assertEquals($expected->getThumbnailQuality(), $actual->getThumbnailQuality());
    }

    private function getSingleFolderFromRepo(string $id): MediaFolderEntity
    {
        return $this->mediaFolderRepo
            ->read(new ReadCriteria([$id]), $this->context)
            ->get($id);
    }

    private function getCollectionFromRepo(array $subFolderIds): MediaFolderCollection
    {
        $postMoveSubFolders = $this->mediaFolderRepo->read(new ReadCriteria($subFolderIds), $this->context);

        return $postMoveSubFolders;
    }
}

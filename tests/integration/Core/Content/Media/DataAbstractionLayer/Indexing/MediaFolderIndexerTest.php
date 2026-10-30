<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Content\Media\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderCollection;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaFolderIndexer;
use Shopware\Core\Content\Media\DataAbstractionLayer\MediaIndexingMessage;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('discovery')]
class MediaFolderIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository<MediaFolderCollection>
     */
    private EntityRepository $mediaFolderRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->mediaFolderRepository = static::getContainer()->get('media_folder.repository');
        $this->context = Context::createDefaultContext();
    }

    public function testIndexSetsCorrectPath(): void
    {
        $depth = 10;
        [$data, $ids] = $this->getData($depth);

        $this->mediaFolderRepository->create([
            $data[$depth],
        ], $this->context);

        $this->assertCorrectPathWithOneSubFolderForEachParent($data, $ids, $depth);
    }

    public function testOnUpdateItSetsCorrectPath(): void
    {
        $depth = 10;
        [$data, $ids] = $this->getData($depth);

        // create structure
        $this->mediaFolderRepository->create([
            $data[$depth],
        ], $this->context);

        // assert structure was created correctly
        $this->assertCorrectPathWithOneSubFolderForEachParent($data, $ids, $depth);

        // old parentIdIndex = 2, new parentIdIndex = 4 --> move two parent up
        $parentIdIndex = 4;

        // move folder two parent up
        $this->mediaFolderRepository->update([
            [
                'id' => $ids[1],
                'parentId' => $ids[$parentIdIndex],
            ],
        ], $this->context);

        // fetch path of folder to move after update
        $pathChildZeroAfterUpdate = $this->getMediaFolderEntityFromId($ids[0])->getPath();
        $pathChildOneAfterUpdate = $this->getMediaFolderEntityFromId($ids[1])->getPath();

        // set expected path for child one
        $expectedPathChildOne = '|';
        for ($i = $depth; $i >= $parentIdIndex; --$i) {
            $expectedPathChildOne .= $ids[$i] . '|';
        }

        // expect child of moved parent to also have the new path as the parent
        $expectedPathChildZero = $expectedPathChildOne . $ids[1] . '|';

        static::assertSame($expectedPathChildOne, $pathChildOneAfterUpdate);
        static::assertSame($expectedPathChildZero, $pathChildZeroAfterUpdate);
    }

    public function testIndexingRestoresThePathOfEveryFolderInTheMessage(): void
    {
        $configurationId = Uuid::randomHex();

        $rootA = Uuid::randomHex();
        $childA = Uuid::randomHex();
        $grandChildA = Uuid::randomHex();
        $rootB = Uuid::randomHex();
        $childB = Uuid::randomHex();

        $this->mediaFolderRepository->create([
            [
                'id' => $rootA,
                'name' => 'root-a',
                'configurationId' => $configurationId,
                'children' => [
                    [
                        'id' => $childA,
                        'name' => 'child-a',
                        'configurationId' => $configurationId,
                        'children' => [
                            ['id' => $grandChildA, 'name' => 'grand-child-a', 'configurationId' => $configurationId],
                        ],
                    ],
                ],
            ],
            [
                'id' => $rootB,
                'name' => 'root-b',
                'configurationId' => $configurationId,
                'children' => [
                    ['id' => $childB, 'name' => 'child-b', 'configurationId' => $configurationId],
                ],
            ],
        ], $this->context);

        $this->moveWithoutIndexing($childA, $rootB);
        $this->moveWithoutIndexing($childB, $rootA);

        $indexer = static::getContainer()->get(MediaFolderIndexer::class);
        $indexer->handle(new MediaIndexingMessage([$rootA, $childA, $grandChildA, $rootB, $childB], null, $this->context));

        static::assertSame('|' . $rootB . '|', $this->fetchPath($childA));
        static::assertSame('|' . $rootB . '|' . $childA . '|', $this->fetchPath($grandChildA));
        static::assertSame('|' . $rootA . '|', $this->fetchPath($childB));
    }

    public function testChildCountIsUpdatedCorrectly(): void
    {
        $parentId = Uuid::randomHex();

        $this->mediaFolderRepository->create([
            [
                'id' => $parentId,
                'name' => 'parent',
                'configurationId' => Uuid::randomHex(),
            ],
        ], $this->context);

        $this->mediaFolderRepository->create([
            [
                'id' => Uuid::randomHex(),
                'name' => 'child',
                'configurationId' => Uuid::randomHex(),
                'parentId' => $parentId,
            ],
        ], $this->context);

        /** @var MediaFolderEntity $folder */
        $folder = $this->mediaFolderRepository->search(new Criteria([$parentId]), $this->context)->getEntities()->first();

        static::assertSame(1, $folder->getChildCount());
    }

    public function testChildCountOfTheOldParentIsUpdatedWhenAFolderMoves(): void
    {
        $oldParentId = Uuid::randomHex();
        $newParentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->mediaFolderRepository->create([
            [
                'id' => $oldParentId,
                'name' => 'old-parent',
                'configurationId' => Uuid::randomHex(),
                'children' => [
                    ['id' => $childId, 'name' => 'child', 'configurationId' => Uuid::randomHex()],
                ],
            ],
            [
                'id' => $newParentId,
                'name' => 'new-parent',
                'configurationId' => Uuid::randomHex(),
            ],
        ], $this->context);

        $this->mediaFolderRepository->update([
            ['id' => $childId, 'parentId' => $newParentId],
        ], $this->context);

        static::assertSame(0, $this->getMediaFolderEntityFromId($oldParentId)->getChildCount());
        static::assertSame(1, $this->getMediaFolderEntityFromId($newParentId)->getChildCount());
    }

    public function testChildCountOfTheParentIsUpdatedWhenAFolderIsDeleted(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $this->mediaFolderRepository->create([
            [
                'id' => $parentId,
                'name' => 'parent',
                'configurationId' => Uuid::randomHex(),
                'children' => [
                    ['id' => $childId, 'name' => 'child', 'configurationId' => Uuid::randomHex()],
                ],
            ],
        ], $this->context);

        static::assertSame(1, $this->getMediaFolderEntityFromId($parentId)->getChildCount());

        $this->mediaFolderRepository->delete([['id' => $childId]], $this->context);

        static::assertSame(0, $this->getMediaFolderEntityFromId($parentId)->getChildCount());
    }

    /**
     * @param list<array<string, list<array<string, list<array<string, list<array<string, string>>|string>>|string>>|string>> $data
     * @param list<string> $ids
     */
    private function assertCorrectPathWithOneSubFolderForEachParent(array $data, array $ids, int $depth): void
    {
        // expect parent path to be null
        $id = $data[$depth]['id'];
        static::assertIsString($id);
        static::assertNull($this->getMediaFolderEntityFromId($id)->getPath());

        $expectedPath = '|' . $ids[$depth] . '|';

        // exclude the parent
        for ($i = $depth - 1; $i >= 0; --$i) {
            $id = $data[$i]['id'];
            static::assertIsString($id);
            $mediaFolderEntity = $this->getMediaFolderEntityFromId($id);

            static::assertSame($ids[$i], $mediaFolderEntity->getId());
            static::assertSame($expectedPath, $mediaFolderEntity->getPath());

            $expectedPath .= $mediaFolderEntity->getId() . '|';
        }
    }

    /**
     * @return array{list<array<string, list<array<string, list<array<string, list<array<string, string>>|string>>|string>>|string>>, list<string>}
     */
    private function getData(int $depth): array
    {
        $configurationId = Uuid::randomHex();
        $data = [];
        $ids = [];

        for ($i = 0; $i <= $depth; ++$i) {
            $ids[] = Uuid::randomHex();

            $data[] = [
                'id' => $ids[$i],
                'name' => 'child-' . $i,
                'configurationId' => $configurationId,
            ];

            if ($i !== 0) {
                $data[$i]['children'] = [$data[$i - 1]];
            }
        }

        return [$data, $ids];
    }

    private function moveWithoutIndexing(string $id, string $parentId): void
    {
        static::getContainer()->get(Connection::class)->executeStatement(
            'UPDATE media_folder SET parent_id = :parentId WHERE id = :id',
            ['parentId' => Uuid::fromHexToBytes($parentId), 'id' => Uuid::fromHexToBytes($id)]
        );
    }

    private function fetchPath(string $id): string
    {
        $path = static::getContainer()->get(Connection::class)->fetchOne(
            'SELECT path FROM media_folder WHERE id = :id',
            ['id' => Uuid::fromHexToBytes($id)]
        );

        static::assertIsString($path);

        return $path;
    }

    private function getMediaFolderEntityFromId(string $id): MediaFolderEntity
    {
        $mediaFolder = $this->mediaFolderRepository->search(new Criteria([$id]), $this->context)->getEntities()->get($id);
        static::assertNotNull($mediaFolder);

        return $mediaFolder;
    }
}

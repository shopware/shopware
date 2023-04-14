<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Media\DataAbstractionLayer\Indexing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Aggregate\MediaFolder\MediaFolderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class MediaFolderIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    private EntityRepository $mediaFolderRepository;

    private Context $context;

    protected function setUp(): void
    {
        $this->mediaFolderRepository = $this->getContainer()->get('media_folder.repository');
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

        static::assertEquals($expectedPathChildOne, $pathChildOneAfterUpdate);
        static::assertEquals($expectedPathChildZero, $pathChildZeroAfterUpdate);
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
        $folder = $this->mediaFolderRepository->search(new Criteria([$parentId]), $this->context)->first();

        static::assertEquals(1, $folder->getChildCount());
    }

    private function assertCorrectPathWithOneSubFolderForEachParent(array $data, array $ids, int $depth): void
    {
        // expect parent path to be null
        static::assertNull($this->getMediaFolderEntityFromId($data[$depth]['id'])->getPath());

        $expectedPath = '|' . $ids[$depth] . '|';

        // exclude the parent
        for ($i = $depth - 1; $i >= 0; --$i) {
            $mediaFolderEntity = $this->getMediaFolderEntityFromId($data[$i]['id']);

            static::assertEquals($ids[$i], $mediaFolderEntity->getId());
            static::assertEquals($expectedPath, $mediaFolderEntity->getPath());

            $expectedPath .= $mediaFolderEntity->getId() . '|';
        }
    }

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

    private function getMediaFolderEntityFromId(string $id): MediaFolderEntity
    {
        return $this->mediaFolderRepository->search(new Criteria([$id]), $this->context)->get($id);
    }
}

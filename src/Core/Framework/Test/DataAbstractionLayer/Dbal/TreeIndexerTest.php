<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\DataAbstractionLayer\CategoryIndexer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexingMessage;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class TreeIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $categoryRepository;

    private Context $context;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var CategoryIndexer
     */
    private $categoryIndexer;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->context = Context::createDefaultContext();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->categoryIndexer = $this->getContainer()->get(CategoryIndexer::class);
    }

    public function testRefreshTree(): void
    {
        /*
        Category A
        ├── Category B
        ├── Category C
        │  └── Category D
        */
        $categoryA = $this->createCategory();

        $categoryB = $this->createCategory($categoryA);
        $categoryC = $this->createCategory($categoryA);

        $categoryD = $this->createCategory($categoryC);

        $categories = $this->categoryRepository->search(new Criteria([$categoryA, $categoryB, $categoryC, $categoryD]), $this->context);

        static::assertNull($categories->get($categoryA)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryB)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryC)->getPath());
        static::assertEquals("|{$categoryA}|{$categoryC}|", $categories->get($categoryD)->getPath());

        static::assertEquals(1, $categories->get($categoryA)->getLevel());
        static::assertEquals(2, $categories->get($categoryB)->getLevel());
        static::assertEquals(2, $categories->get($categoryC)->getLevel());
        static::assertEquals(3, $categories->get($categoryD)->getLevel());

        $this->categoryRepository->update([[
            'id' => $categoryD,
            'parentId' => $categoryA,
        ]], $this->context);

        /*
        Category A
        ├── Category B
        ├── Category C
        ├── Category D
        */

        $categories = $this->categoryRepository->search(new Criteria([$categoryA, $categoryB, $categoryC, $categoryD]), $this->context);

        static::assertNull($categories->get($categoryA)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryB)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryC)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryD)->getPath());

        static::assertEquals(1, $categories->get($categoryA)->getLevel());
        static::assertEquals(2, $categories->get($categoryB)->getLevel());
        static::assertEquals(2, $categories->get($categoryC)->getLevel());
        static::assertEquals(2, $categories->get($categoryD)->getLevel());
    }

    public function testRefreshTreeMovingMultipleCategories(): void
    {
        /*
        Category A
        ├── Category B
        │  └── Category C
        ├── Category D
        │  └── Category E
        */
        $categoryA = $this->createCategory();
        $categoryB = $this->createCategory($categoryA);
        $categoryC = $this->createCategory($categoryB);

        $categoryD = $this->createCategory($categoryA);
        $categoryE = $this->createCategory($categoryD);

        $categories = $this->categoryRepository->search(
            new Criteria([$categoryA, $categoryB, $categoryC, $categoryD, $categoryE]),
            $this->context
        );

        static::assertNull($categories->get($categoryA)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryB)->getPath());
        static::assertEquals("|{$categoryA}|{$categoryB}|", $categories->get($categoryC)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryD)->getPath());
        static::assertEquals("|{$categoryA}|{$categoryD}|", $categories->get($categoryE)->getPath());

        static::assertEquals(1, $categories->get($categoryA)->getLevel());
        static::assertEquals(2, $categories->get($categoryB)->getLevel());
        static::assertEquals(3, $categories->get($categoryC)->getLevel());
        static::assertEquals(2, $categories->get($categoryD)->getLevel());
        static::assertEquals(3, $categories->get($categoryE)->getLevel());

        $this->categoryRepository->update([
            [
                'id' => $categoryC,
                'parentId' => $categoryA,
            ],
            [
                'id' => $categoryD,
                'parentId' => $categoryC,
            ],
            [
                'id' => $categoryE,
                'parentId' => $categoryC,
            ],
        ], $this->context);

        /**
        Category A
        ├── Category B
        ├── Category C
        │  └── Category D
        │  └── Category E
         */
        $categories = $this->categoryRepository->search(
            new Criteria([$categoryA, $categoryB, $categoryC, $categoryD, $categoryE]),
            $this->context
        );

        static::assertNull($categories->get($categoryA)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryB)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryC)->getPath());
        static::assertEquals("|{$categoryA}|{$categoryC}|", $categories->get($categoryD)->getPath());
        static::assertEquals("|{$categoryA}|{$categoryC}|", $categories->get($categoryE)->getPath());

        static::assertEquals(1, $categories->get($categoryA)->getLevel());
        static::assertEquals(2, $categories->get($categoryB)->getLevel());
        static::assertEquals(2, $categories->get($categoryC)->getLevel());
        static::assertEquals(3, $categories->get($categoryD)->getLevel());
        static::assertEquals(3, $categories->get($categoryE)->getLevel());
    }

    public function testRefreshTreeWithDifferentVersion(): void
    {
        /*
        Category A
        ├── Category B
        ├── Category C
        │  └── Category D
        */
        $categoryA = $this->createCategory();

        $categoryB = $this->createCategory($categoryA);
        $categoryC = $this->createCategory($categoryA);

        $categoryD = $this->createCategory($categoryC);

        $categories = $this->categoryRepository->search(new Criteria([$categoryA, $categoryB, $categoryC, $categoryD]), $this->context);

        static::assertNull($categories->get($categoryA)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryB)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryC)->getPath());
        static::assertEquals("|{$categoryA}|{$categoryC}|", $categories->get($categoryD)->getPath());

        static::assertEquals(1, $categories->get($categoryA)->getLevel());
        static::assertEquals(2, $categories->get($categoryB)->getLevel());
        static::assertEquals(2, $categories->get($categoryC)->getLevel());
        static::assertEquals(3, $categories->get($categoryD)->getLevel());

        $versionId = $this->categoryRepository->createVersion($categoryD, $this->context);
        $versionContext = $this->context->createWithVersionId($versionId);

        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search(new Criteria([$categoryD]), $versionContext)->first();
        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertEquals('|' . $categoryA . '|' . $categoryC . '|', $category->getPath());

        //update parent of last category in version scope
        $updated = ['id' => $categoryD, 'parentId' => $categoryA];

        $this->categoryRepository->update([$updated], $versionContext);

        /** @var CategoryEntity $category */
        //check that the path updated
        $category = $this->categoryRepository->search(new Criteria([$categoryD]), $versionContext)->first();
        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertEquals('|' . $categoryA . '|', $category->getPath());

        $category = $this->categoryRepository->search(new Criteria([$categoryD]), $this->context)->first();
        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertEquals('|' . $categoryA . '|' . $categoryC . '|', $category->getPath());

        $this->categoryRepository->merge($versionId, $this->context);

        //test after merge the path is updated too
        /** @var CategoryEntity $category */
        $category = $this->categoryRepository->search(new Criteria([$categoryD]), $this->context)->first();
        static::assertInstanceOf(CategoryEntity::class, $category);
        static::assertEquals('|' . $categoryA . '|', $category->getPath());
    }

    public function testIndexTree(): void
    {
        /*
        Category A
        ├── Category B
        ├── Category C
        │  └── Category D
        */

        $categoryA = $this->createCategory();
        $categoryB = $this->createCategory($categoryA);
        $categoryC = $this->createCategory($categoryA);
        $categoryD = $this->createCategory($categoryC);

        $this->connection->executeStatement(
            'UPDATE category SET path = NULL, level = 0 WHERE HEX(id) IN (:ids)',
            [
                'ids' => [
                    $categoryA,
                    $categoryB,
                    $categoryC,
                    $categoryD,
                ],
            ],
            ['ids' => ArrayParameterType::STRING]
        );

        $categories = $this->categoryRepository->search(new Criteria([$categoryA, $categoryB, $categoryC, $categoryD]), $this->context);
        foreach ($categories as $category) {
            static::assertEquals(0, $category->getLevel());
            static::assertNull($category->getPath());
        }

        $this->categoryIndexer->handle(
            new EntityIndexingMessage([$categoryA, $categoryB, $categoryC, $categoryD])
        );

        $categories = $this->categoryRepository->search(new Criteria([$categoryA, $categoryB, $categoryC, $categoryD]), $this->context);

        static::assertNull($categories->get($categoryA)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryB)->getPath());
        static::assertEquals("|{$categoryA}|", $categories->get($categoryC)->getPath());
        static::assertEquals("|{$categoryA}|{$categoryC}|", $categories->get($categoryD)->getPath());

        static::assertEquals(1, $categories->get($categoryA)->getLevel());
        static::assertEquals(2, $categories->get($categoryB)->getLevel());
        static::assertEquals(2, $categories->get($categoryC)->getLevel());
        static::assertEquals(3, $categories->get($categoryD)->getLevel());
    }

    private function createCategory(?string $parentId = null): string
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'Category ' . $id,
        ];

        if ($parentId) {
            $data['parentId'] = $parentId;
        }
        $this->categoryRepository->upsert([$data], $this->context);

        return $id;
    }
}

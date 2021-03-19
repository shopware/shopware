<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ChildCountIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepositoryInterface
     */
    private $categoryRepository;

    /**
     * @var Context
     */
    private $context;

    /**
     * @var ChildCountUpdater
     */
    private $childCountIndexer;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var Connection
     */
    private $connection;

    protected function setUp(): void
    {
        $this->categoryRepository = $this->getContainer()->get('category.repository');
        $this->context = Context::createDefaultContext();
        $this->childCountIndexer = $this->getContainer()->get(ChildCountUpdater::class);
        $this->eventDispatcher = $this->getContainer()->get('event_dispatcher');
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testCreateChildCategory(): void
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

        static::assertEquals(2, $categories->get($categoryA)->getChildCount());
        static::assertEquals(0, $categories->get($categoryB)->getChildCount());
        static::assertEquals(1, $categories->get($categoryC)->getChildCount());
        static::assertEquals(0, $categories->get($categoryD)->getChildCount());

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

        static::assertEquals(3, $categories->get($categoryA)->getChildCount());
        static::assertEquals(0, $categories->get($categoryB)->getChildCount());
        static::assertEquals(0, $categories->get($categoryC)->getChildCount());
        static::assertEquals(0, $categories->get($categoryD)->getChildCount());
    }

    public function testChildCountCategoryMovingMultipleCategories(): void
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

        static::assertEquals(2, $categories->get($categoryA)->getChildCount());
        static::assertEquals(1, $categories->get($categoryB)->getChildCount());
        static::assertEquals(0, $categories->get($categoryC)->getChildCount());
        static::assertEquals(1, $categories->get($categoryD)->getChildCount());
        static::assertEquals(0, $categories->get($categoryE)->getChildCount());

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

        static::assertEquals(2, $categories->get($categoryA)->getChildCount());
        static::assertEquals(0, $categories->get($categoryB)->getChildCount());
        static::assertEquals(2, $categories->get($categoryC)->getChildCount());
        static::assertEquals(0, $categories->get($categoryD)->getChildCount());
        static::assertEquals(0, $categories->get($categoryE)->getChildCount());
    }

    public function testChildCountIndexer(): void
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

        $this->connection->executeUpdate(
            'UPDATE category SET child_count = 0 WHERE id IN (:ids)',
            [
                'ids' => Uuid::fromHexToBytesList([
                    $categoryA,
                    $categoryB,
                    $categoryC,
                    $categoryD,
                ]),
            ],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );

        $categories = $this->categoryRepository->search(new Criteria([$categoryA, $categoryB, $categoryC, $categoryD]), $this->context);

        foreach ($categories as $category) {
            static::assertEquals(0, $category->getChildCount());
        }

        $this->childCountIndexer->update(CategoryDefinition::ENTITY_NAME, [$categoryA, $categoryB, $categoryC, $categoryD], $this->context);

        $categories = $this->categoryRepository->search(new Criteria([$categoryA, $categoryB, $categoryC, $categoryD]), $this->context);

        static::assertEquals(2, $categories->get($categoryA)->getChildCount());
        static::assertEquals(0, $categories->get($categoryB)->getChildCount());
        static::assertEquals(1, $categories->get($categoryC)->getChildCount());
        static::assertEquals(0, $categories->get($categoryD)->getChildCount());
    }

    private function createCategory(?string $parentId = null): string
    {
        $id = Uuid::randomHex();
        $data = [
            'id' => $id,
            'name' => 'Category ',
        ];

        if ($parentId) {
            $data['parentId'] = $parentId;
        }
        $this->categoryRepository->upsert([$data], $this->context);

        return $id;
    }
}

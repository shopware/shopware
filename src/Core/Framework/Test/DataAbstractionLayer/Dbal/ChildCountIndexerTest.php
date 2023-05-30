<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Dbal;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\ChildCountUpdater;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
class ChildCountIndexerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var EntityRepository
     */
    private $categoryRepository;

    private Context $context;

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

        $this->connection->executeStatement(
            'UPDATE category SET child_count = 0 WHERE id IN (:ids)',
            [
                'ids' => Uuid::fromHexToBytesList([
                    $categoryA,
                    $categoryB,
                    $categoryC,
                    $categoryD,
                ]),
            ],
            ['ids' => ArrayParameterType::STRING]
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

    public function testDeleteProductWithRecalculatedChildCount(): void
    {
        $ids = new IdsCollection();

        $products = [
            (new ProductBuilder($ids, 'parent'))
                ->price(100)
                ->variant((new ProductBuilder($ids, 'variant-1'))->price(200)->build())
                ->variant((new ProductBuilder($ids, 'variant-2'))->price(200)->build())
                ->build(),
        ];

        $this->getContainer()->get('product.repository')->create($products, Context::createDefaultContext());

        $count = $this->connection->fetchOne('SELECT child_count FROM product WHERE id = :id', ['id' => $ids->getBytes('parent')]);
        static::assertEquals(2, $count);

        $this->getContainer()->get('product.repository')->delete([['id' => $ids->get('variant-1')]], Context::createDefaultContext());
        $count = $this->connection->fetchOne('SELECT child_count FROM product WHERE id = :id', ['id' => $ids->getBytes('parent')]);
        static::assertEquals(1, $count);

        $this->getContainer()->get('product.repository')->delete([['id' => $ids->get('variant-2')]], Context::createDefaultContext());
        $count = $this->connection->fetchOne('SELECT child_count FROM product WHERE id = :id', ['id' => $ids->getBytes('parent')]);
        static::assertEquals(0, $count);
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

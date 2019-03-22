<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\Uuid;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;

class EntitySearcherTest extends TestCase
{
    use IntegrationTestBehaviour;
    /**
     * @var EntityRepositoryInterface
     */
    private $groupRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $productRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->groupRepository = $this->getContainer()->get('configuration_group.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
    }

    public function testTotalCountWithSearchTerm()
    {
        $id1 = Uuid::uuid4()->getHex();
        $id2 = Uuid::uuid4()->getHex();

        $products = [
            [
                'id' => $id1,
                'name' => 'test matching product',
                'stock' => 10,
                'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
            [
                'id' => $id2,
                'name' => 'test matching',
                'stock' => 10,
                'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
        ];

        $context = Context::createDefaultContext();
        $this->productRepository->create($products, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.id', [$id1, $id2]));
        $criteria->addQuery(
            new ScoreQuery(new ContainsFilter('product.name', 'matching'), 1000)
        );
        $criteria->addQuery(
            new ScoreQuery(new ContainsFilter('product.name', 'test matching'), 1000)
        );

        $criteria->addQuery(
            new ScoreQuery(new ContainsFilter('product.name', 'matching product'), 1000)
        );

        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);
        $result = $this->productRepository->search($criteria, $context);

        static::assertSame(2, $result->getTotal());
        static::assertCount(2, $result->getEntities());
    }

    public function testSortingAndTotalCountWithManyAssociation()
    {
        $redId = Uuid::uuid4()->getHex();
        $greenId = Uuid::uuid4()->getHex();
        $yellowId = Uuid::uuid4()->getHex();
        $colorId = Uuid::uuid4()->getHex();

        $sizeId = Uuid::uuid4()->getHex();
        $bigId = Uuid::uuid4()->getHex();
        $smallId = Uuid::uuid4()->getHex();

        $id = Uuid::uuid4()->getHex();
        $variant1 = Uuid::uuid4()->getHex();
        $variant2 = Uuid::uuid4()->getHex();
        $variant3 = Uuid::uuid4()->getHex();
        $variant4 = Uuid::uuid4()->getHex();
        $variant5 = Uuid::uuid4()->getHex();
        $variant6 = Uuid::uuid4()->getHex();

        $context = Context::createDefaultContext();

        $groups = [
            [
                'id' => $colorId,
                'name' => 'color',
                'options' => [
                    ['id' => $redId, 'name' => 'red'],
                    ['id' => $yellowId, 'name' => 'red'],
                    ['id' => $greenId, 'name' => 'red'],
                ],
            ],
            [
                'id' => $sizeId,
                'name' => 'size',
                'options' => [
                    ['id' => $bigId, 'name' => 'big'],
                    ['id' => $smallId, 'name' => 'small'],
                ],
            ],
        ];

        $this->groupRepository->create($groups, $context);

        $products = [
            [
                'id' => $id,
                'name' => 'test',
                'stock' => 10,
                'price' => ['gross' => 15, 'net' => 10, 'linked' => false],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
            [
                'id' => $variant1,
                'parentId' => $id,
                'stock' => 10,
                'variations' => [['id' => $redId], ['id' => $bigId]],
            ],
            [
                'id' => $variant2,
                'parentId' => $id,
                'stock' => 10,
                'variations' => [['id' => $redId], ['id' => $smallId]],
            ],
            [
                'id' => $variant3,
                'parentId' => $id,
                'stock' => 10,
                'variations' => [['id' => $greenId], ['id' => $bigId]],
            ],
            [
                'id' => $variant4,
                'parentId' => $id,
                'stock' => 10,
                'variations' => [['id' => $greenId], ['id' => $smallId]],
            ],
            [
                'id' => $variant5,
                'parentId' => $id,
                'stock' => 10,
                'variations' => [['id' => $yellowId], ['id' => $bigId]],
            ],
            [
                'id' => $variant6,
                'parentId' => $id,
                'stock' => 10,
                'variations' => [['id' => $yellowId], ['id' => $smallId]],
            ],
        ];

        $this->productRepository->create($products, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $id));
        $criteria->addSorting(new FieldSorting('product.variations.groupId'));
        $criteria->addSorting(new FieldSorting('product.variations.id'));

        $criteria->setLimit(25);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->productRepository->search($criteria, $context);

        static::assertSame(6, $result->getTotal());
        static::assertCount(6, $result->getEntities());
    }
}

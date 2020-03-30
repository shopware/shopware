<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\DataAbstractionLayer\Search;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntitySearcher;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Parser\SqlQueryParser;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\EntityScoreQueryBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Term\SearchTermInterpreter;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestDataCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Tax\TaxDefinition;

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

        $this->groupRepository = $this->getContainer()->get('property_group.repository');
        $this->productRepository = $this->getContainer()->get('product.repository');
    }

    public function testTotalCountWithSearchTerm(): void
    {
        $id1 = Uuid::randomHex();
        $id2 = Uuid::randomHex();

        $products = [
            [
                'id' => $id1,
                'productNumber' => Uuid::randomHex(),
                'name' => 'test matching product',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
            [
                'id' => $id2,
                'productNumber' => Uuid::randomHex(),
                'name' => 'test matching',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
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

    public function testSortingAndTotalCountWithManyAssociation(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $yellowId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $sizeId = Uuid::randomHex();
        $bigId = Uuid::randomHex();
        $smallId = Uuid::randomHex();

        $id = Uuid::randomHex();
        $variant1 = Uuid::randomHex();
        $variant2 = Uuid::randomHex();
        $variant3 = Uuid::randomHex();
        $variant4 = Uuid::randomHex();
        $variant5 = Uuid::randomHex();
        $variant6 = Uuid::randomHex();

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
                'productNumber' => Uuid::randomHex(),
                'name' => 'test',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
            [
                'id' => $variant1,
                'productNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $redId], ['id' => $bigId]],
            ],
            [
                'id' => $variant2,
                'productNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $redId], ['id' => $smallId]],
            ],
            [
                'id' => $variant3,
                'productNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $greenId], ['id' => $bigId]],
            ],
            [
                'id' => $variant4,
                'productNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $greenId], ['id' => $smallId]],
            ],
            [
                'id' => $variant5,
                'productNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $yellowId], ['id' => $bigId]],
            ],
            [
                'id' => $variant6,
                'productNumber' => Uuid::randomHex(),
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $yellowId], ['id' => $smallId]],
            ],
        ];

        $this->productRepository->create($products, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('product.parentId', $id));
        $criteria->addSorting(new FieldSorting('product.options.groupId'));
        $criteria->addSorting(new FieldSorting('product.options.id'));

        $criteria->setLimit(25);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->productRepository->search($criteria, $context);

        static::assertSame(6, $result->getTotal());
        static::assertCount(6, $result->getEntities());
    }

    public function testJsonListEqualsAnyFilter(): void
    {
        $redId = Uuid::randomHex();
        $greenId = Uuid::randomHex();
        $yellowId = Uuid::randomHex();
        $colorId = Uuid::randomHex();

        $sizeId = Uuid::randomHex();
        $bigId = Uuid::randomHex();
        $smallId = Uuid::randomHex();

        $id = Uuid::randomHex();
        $variant1 = Uuid::randomHex();
        $variant2 = Uuid::randomHex();
        $variant3 = Uuid::randomHex();
        $variant4 = Uuid::randomHex();
        $variant5 = Uuid::randomHex();
        $variant6 = Uuid::randomHex();

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
                'productNumber' => 'test1',
                'stock' => 10,
                'price' => [['currencyId' => Defaults::CURRENCY, 'gross' => 15, 'net' => 10, 'linked' => false]],
                'manufacturer' => ['name' => 'test'],
                'tax' => ['name' => 'test', 'taxRate' => 15],
            ],
            [
                'id' => $variant1,
                'productNumber' => 'test2',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $redId], ['id' => $bigId]],
            ],
            [
                'id' => $variant2,
                'productNumber' => 'test3',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $redId], ['id' => $smallId]],
            ],
            [
                'id' => $variant3,
                'productNumber' => 'test4',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $greenId], ['id' => $bigId]],
            ],
            [
                'id' => $variant4,
                'productNumber' => 'test5',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $greenId], ['id' => $smallId]],
            ],
            [
                'id' => $variant5,
                'productNumber' => 'test6',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $yellowId], ['id' => $bigId]],
            ],
            [
                'id' => $variant6,
                'productNumber' => 'test7',
                'parentId' => $id,
                'stock' => 10,
                'options' => [['id' => $yellowId], ['id' => $smallId]],
            ],
        ];

        $this->productRepository->create($products, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.optionIds', [$yellowId, $redId]));

        $result = $this->productRepository->search($criteria, $context);

        static::assertSame(4, $result->getTotal());
        static::assertTrue($result->has($variant1));
        static::assertTrue($result->has($variant2));
        static::assertTrue($result->has($variant5));
        static::assertTrue($result->has($variant6));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.optionIds', [$yellowId]));

        $result = $this->productRepository->search($criteria, $context);
        static::assertSame(2, $result->getTotal());
        static::assertTrue($result->has($variant5));
        static::assertTrue($result->has($variant6));

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.optionIds', [$yellowId, $smallId]));

        $result = $this->productRepository->search($criteria, $context);
        static::assertSame(4, $result->getTotal());
        static::assertTrue($result->has($variant5));
        static::assertTrue($result->has($variant6));
        static::assertTrue($result->has($variant4));
        static::assertTrue($result->has($variant2));
    }

    public function testSortingByProvidedIds(): void
    {
        $ids = new TestDataCollection();

        $data = [
            ['id' => $ids->create('t1'), 'name' => 'tax 1', 'taxRate' => 10],
            ['id' => $ids->create('t2'), 'name' => 'tax 2', 'taxRate' => 10],
            ['id' => $ids->create('t3'), 'name' => 'tax 3', 'taxRate' => 10],
            ['id' => $ids->create('t4'), 'name' => 'tax 4', 'taxRate' => 10],
        ];

        $this->getContainer()->get('tax.repository')
            ->create($data, Context::createDefaultContext());

        $searcher = new EntitySearcher(
            $this->getContainer()->get(Connection::class),
            $this->getContainer()->get(SqlQueryParser::class),
            $this->getContainer()->get(EntityDefinitionQueryHelper::class),
            $this->getContainer()->get(SearchTermInterpreter::class),
            $this->getContainer()->get(EntityScoreQueryBuilder::class)
        );

        $expected = [
            $ids->get('t4'),
            $ids->get('t2'),
            $ids->get('t1'),
            $ids->get('t3'),
        ];

        $criteria = new Criteria($expected);
        $criteria->addFilter(new EqualsFilter('taxRate', 10));

        $result = $searcher->search($this->getContainer()->get(TaxDefinition::class), $criteria, Context::createDefaultContext());

        static::assertEquals($expected, $result->getIds());
    }
}

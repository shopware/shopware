<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test\Product;

use Doctrine\DBAL\Connection;
use Elasticsearch\Client;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\ValueAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\ValueResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\ElasticsearchHelper;
use Shopware\Elasticsearch\Test\ElasticsearchTestTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ElasticsearchProductTest extends TestCase
{
    use ElasticsearchTestTestBehaviour;

    /**
     * @var Client
     */
    private $client;

    /**
     * @var ProductDefinition
     */
    private $productDefinition;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    /**
     * @var ElasticsearchHelper
     */
    private $helper;

    protected function setUp(): void
    {
        $this->helper = $this->getContainer()->get(ElasticsearchHelper::class);
        $this->client = $this->getContainer()->get(Client::class);
        $this->productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $this->languageRepository = $this->getContainer()->get('language.repository');
    }

    public function testIndexing()
    {
        $this->getContainer()->get(Connection::class)->executeUpdate('DELETE FROM product');

        $context = Context::createDefaultContext();

        //Instead of indexing the test data in the set-up, we index it in the first test method. So this data does not have to be indexed again in each test.
        $data = $this->createData();

        $this->indexElasticSearch();

        $data->setContext($context);

        $languages = $this->languageRepository->searchIds(new Criteria(), $context);

        foreach ($languages->getIds() as $languageId) {
            $index = $this->helper->getIndexName($this->productDefinition, $languageId);

            $exists = $this->client->indices()->exists(['index' => $index]);
            static::assertTrue($exists);

            $exists = $this->client->exists(['index' => $index, 'id' => $data->getProductId('product1')]);
            static::assertTrue($exists);

            $exists = $this->client->exists(['index' => $index, 'id' => $data->getProductId('product2')]);
            static::assertTrue($exists);

            $exists = $this->client->exists(['index' => $index, 'id' => $data->getProductId('product3')]);
            static::assertTrue($exists);
        }

        return $data;
    }

    /**
     * @depends testIndexing
     */
    public function testEmptySearch(ElasticsearchTestData $data)
    {
        $searcher = $this->createEntitySearcher();

        // check simple search without any restrictions
        $criteria = new Criteria($data->getProductIds());
        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(3, $products->getIds());
    }

    /**
     * @depends testIndexing
     */
    public function testPagination(ElasticsearchTestData $data)
    {
        $searcher = $this->createEntitySearcher();

        // check pagination
        $criteria = new Criteria($data->getProductIds());
        $criteria->setLimit(1);

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(1, $products->getIds());
        static::assertSame(3, $products->getTotal());
    }

    /**
     * @depends testIndexing
     */
    public function testEqualsFilter(ElasticsearchTestData $data)
    {
        $searcher = $this->createEntitySearcher();
        // check simple equals filter
        $criteria = new Criteria($data->getProductIds());
        $criteria->addFilter(new EqualsFilter('stock', 2));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(1, $products->getIds());
        static::assertSame(1, $products->getTotal());
    }

    /**
     * @depends testIndexing
     */
    public function testRangeFilter(ElasticsearchTestData $data)
    {
        $searcher = $this->createEntitySearcher();
        // check simple range filter
        $criteria = new Criteria($data->getProductIds());
        $criteria->addFilter(new RangeFilter('product.stock', [RangeFilter::GTE => 10]));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(2, $products->getIds());
        static::assertSame(2, $products->getTotal());
    }

    /**
     * @depends testIndexing
     */
    public function testEqualsAnyFilter(ElasticsearchTestData $data)
    {
        $searcher = $this->createEntitySearcher();
        // check filter for categories
        $criteria = new Criteria($data->getProductIds());
        $criteria->addFilter(new EqualsAnyFilter('product.categoriesRo.id', [$data->getCategoryId('category1')]));

        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(1, $products->getIds());
        static::assertSame(1, $products->getTotal());
        static::assertContains($data->getProductId('product1'), $products->getIds());
    }

    /**
     * @depends testIndexing
     */
    public function testValueAggregation(ElasticsearchTestData $data)
    {
        $aggregator = $this->createEntityAggregator();
        $criteria = new Criteria($data->getProductIds());
        $criteria->addAggregation(new ValueAggregation('product.stock', 'stock'));

        $result = $aggregator->aggregate($this->productDefinition, $criteria, $data->getContext());
        static::assertTrue($result->getAggregations()->has('stock'));
        $aggregation = $result->getAggregations()->get('stock');

        static::assertInstanceOf(AggregationResult::class, $aggregation);
        $stock = $aggregation->get(null);
        static::assertInstanceOf(ValueResult::class, $stock);

        /** @var ValueResult $stock */
        static::assertCount(3, $stock->getValues());
        static::assertEquals([2, 10, 200], $stock->getValues());
    }

    /**
     * @depends testIndexing
     */
    public function testQueries(ElasticsearchTestData $data)
    {
        $searcher = $this->createEntitySearcher();
        $criteria = new Criteria($data->getProductIds());
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('product.name', 'Silk'), 1000));
        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(2, $products->getIds());
        static::assertContains($data->getProductId('product1'), $products->getIds());
        static::assertContains($data->getProductId('product3'), $products->getIds());

        $searcher = $this->createEntitySearcher();
        $criteria = new Criteria($data->getProductIds());
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('product.name', 'Slik'), 1000));
        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(2, $products->getIds());
        static::assertContains($data->getProductId('product1'), $products->getIds());
        static::assertContains($data->getProductId('product3'), $products->getIds());

        $searcher = $this->createEntitySearcher();
        $criteria = new Criteria($data->getProductIds());
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('product.name', 'Skill'), 1000));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('product.name', 'Rubar'), 1000));
        $products = $searcher->search($this->productDefinition, $criteria, $data->getContext());
        static::assertCount(3, $products->getIds());
        static::assertContains($data->getProductId('product1'), $products->getIds());
        static::assertContains($data->getProductId('product2'), $products->getIds());
        static::assertContains($data->getProductId('product3'), $products->getIds());
    }

    protected function getDiContainer(): ContainerInterface
    {
        return $this->getContainer();
    }

    private function createProduct(array $data = [])
    {
        $id = Uuid::randomHex();

        $defaults = [
            'id' => $id,
            'name' => 'test',
            'price' => [
                ['currencyId' => Defaults::CURRENCY, 'gross' => 100, 'net' => 100, 'linked' => false],
            ],
            'stock' => 100,
            'productNumber' => $id,
            // use always the same manufacturer
            'manufacturer' => ['id' => Defaults::CURRENCY, 'name' => 'example manufacturer'],
            'tax' => ['id' => Defaults::CURRENCY, 'taxRate' => 19, 'name' => 'example tax'],
        ];

        $data = array_replace_recursive($defaults, $data);

        $this->getContainer()->get('product.repository')
            ->create([$data], Context::createDefaultContext());

        return $id;
    }

    private function createData(): ElasticsearchTestData
    {
        $category1 = Uuid::randomHex();

        $product1 = $this->createProduct(
            [
                'name' => 'Silk',
                'stock' => 2,
                'categories' => [
                    ['id' => $category1, 'name' => 'test'],
                ],
            ]
        );
        $product2 = $this->createProduct(['name' => 'Rubber', 'stock' => 10]);
        $product3 = $this->createProduct(['name' => 'Stilk', 'stock' => 200]);

        $data = new ElasticsearchTestData();

        $data
            ->addCategoryId('category1', $category1)
            ->addProductId('product1', $product1)
            ->addProductId('product2', $product2)
            ->addProductId('product3', $product3);

        return $data;
    }
}

class ElasticsearchTestData
{
    /**
     * @var array
     */
    protected $productIds = [];

    /**
     * @var array
     */
    protected $categoryIds = [];

    /**
     * @var Context
     */
    protected $context;

    public function getProductIds(): array
    {
        return $this->productIds;
    }

    public function getCategoryIds(): array
    {
        return $this->categoryIds;
    }

    public function addProductId(string $key, string $productId): self
    {
        $this->productIds[$key] = $productId;

        return $this;
    }

    public function addCategoryId(string $key, string $categoryId): self
    {
        $this->categoryIds[$key] = $categoryId;

        return $this;
    }

    public function getProductId(string $key)
    {
        return $this->productIds[$key];
    }

    public function getCategoryId(string $key)
    {
        return $this->categoryIds[$key];
    }

    public function setContext(Context $context)
    {
        $this->context = $context;
    }

    public function getContext(): Context
    {
        return $this->context;
    }
}

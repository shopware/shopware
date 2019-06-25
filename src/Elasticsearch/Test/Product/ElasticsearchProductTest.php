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

    public function testProducts()
    {
        $this->getContainer()->get(Connection::class)->executeUpdate('DELETE FROM product');

        $cat1 = Uuid::randomHex();

        $product1 = $this->createProduct([
            'name' => 'Silk',
            'stock' => 2,
            'categories' => [
                ['id' => $cat1, 'name' => 'test'],
            ],
        ]);
        $product2 = $this->createProduct(['name' => 'Rubber', 'stock' => 10]);
        $product3 = $this->createProduct(['name' => 'Stilk', 'stock' => 200]);

        $this->indexElasticSearch();

        $context = Context::createDefaultContext();

        $languages = $this->languageRepository->searchIds(new Criteria(), $context);

        foreach ($languages->getIds() as $languageId) {
            $index = $this->helper->getIndexName($this->productDefinition, $languageId);

            $exists = $this->client->indices()->exists(['index' => $index]);
            static::assertTrue($exists);

            $exists = $this->client->exists(['index' => $index, 'id' => $product1]);
            static::assertTrue($exists);

            $exists = $this->client->exists(['index' => $index, 'id' => $product2]);
            static::assertTrue($exists);

            $exists = $this->client->exists(['index' => $index, 'id' => $product3]);
            static::assertTrue($exists);
        }

        $searcher = $this->createEntitySearcher();
        $aggregator = $this->createEntityAggregator();

        // check simple search without any restrictions
        $criteria = new Criteria();
        $products = $searcher->search($this->productDefinition, $criteria, $context);
        static::assertCount(3, $products->getIds());

        // check pagination
        $criteria = new Criteria();
        $criteria->setLimit(1);

        $products = $searcher->search($this->productDefinition, $criteria, $context);
        static::assertCount(1, $products->getIds());
        static::assertSame(3, $products->getTotal());

        // check simple equals filter
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('stock', 2));

        $products = $searcher->search($this->productDefinition, $criteria, $context);
        static::assertCount(1, $products->getIds());
        static::assertSame(1, $products->getTotal());

        // check simple range filter
        $criteria = new Criteria();
        $criteria->addFilter(new RangeFilter('product.stock', [RangeFilter::GTE => 10]));

        $products = $searcher->search($this->productDefinition, $criteria, $context);
        static::assertCount(2, $products->getIds());
        static::assertSame(2, $products->getTotal());

        // check filter for categories
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsAnyFilter('product.categoriesRo.id', [$cat1]));

        $products = $searcher->search($this->productDefinition, $criteria, $context);
        static::assertCount(1, $products->getIds());
        static::assertSame(1, $products->getTotal());
        static::assertContains($product1, $products->getIds());

        $criteria = new Criteria();
        $criteria->addAggregation(new ValueAggregation('product.stock', 'stock'));

        $result = $aggregator->aggregate($this->productDefinition, $criteria, $context);
        static::assertTrue($result->getAggregations()->has('stock'));
        $aggregation = $result->getAggregations()->get('stock');

        static::assertInstanceOf(AggregationResult::class, $aggregation);
        $stock = $aggregation->get(null);
        static::assertInstanceOf(ValueResult::class, $stock);

        /** @var ValueResult $stock */
        static::assertCount(3, $stock->getValues());
        static::assertEquals([2, 10, 200], $stock->getValues());

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('product.name', 'Silk'), 1000));
        $products = $searcher->search($this->productDefinition, $criteria, $context);
        static::assertCount(2, $products->getIds());
        static::assertContains($product1, $products->getIds());
        static::assertContains($product3, $products->getIds());

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('product.name', 'Slik'), 1000));
        $products = $searcher->search($this->productDefinition, $criteria, $context);
        static::assertCount(2, $products->getIds());
        static::assertContains($product1, $products->getIds());
        static::assertContains($product3, $products->getIds());

        $criteria = new Criteria();
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('product.name', 'Skill'), 1000));
        $criteria->addQuery(new ScoreQuery(new ContainsFilter('product.name', 'Rubar'), 1000));
        $products = $searcher->search($this->productDefinition, $criteria, $context);
        static::assertCount(3, $products->getIds());
        static::assertContains($product1, $products->getIds());
        static::assertContains($product2, $products->getIds());
        static::assertContains($product3, $products->getIds());
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
}

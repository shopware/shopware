<?php declare(strict_types=1);

namespace Shopware\Elasticsearch\Test;

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
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Elasticsearch\Framework\DefinitionRegistry;

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
     * @var DefinitionRegistry
     */
    private $registry;

    /**
     * @var EntityRepositoryInterface
     */
    private $languageRepository;

    protected function setUp(): void
    {
        $this->client = $this->getContainer()->get(Client::class);
        $this->registry = $this->getContainer()->get(DefinitionRegistry::class);
        $this->productDefinition = $this->getContainer()->get(ProductDefinition::class);
        $this->languageRepository = $this->getContainer()->get('language.repository');
    }

    public function testProducts()
    {
        $this->getContainer()->get(Connection::class)->executeUpdate('DELETE FROM product');

        $product1 = $this->createProduct(['stock' => 2]);
        $product2 = $this->createProduct(['stock' => 10]);
        $product3 = $this->createProduct(['stock' => 200]);

        $this->indexElasticSearch();

        $context = Context::createDefaultContext();

        $languages = $this->languageRepository->searchIds(new Criteria(), $context);

        foreach ($languages->getIds() as $languageId) {
            $index = $this->registry->getIndex($this->productDefinition, $languageId);

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
    }

    private function createProduct(array $data = [])
    {
        $id = Uuid::randomHex();

        $defaults = [
            'id' => $id,
            'name' => 'test',
            'price' => ['gross' => 100, 'net' => 100, 'linked' => false],
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

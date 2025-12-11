<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Elasticsearch\Admin;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Test\TestCaseBase\AdminApiTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\QueueTestBehaviour;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Elasticsearch\Admin\AdminSearcher;
use Shopware\Elasticsearch\Test\AdminElasticsearchTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * @internal
 */
class AdminSearcherTest extends TestCase
{
    use AdminApiTestBehaviour;
    use AdminElasticsearchTestBehaviour;
    use KernelTestBehaviour;
    use QueueTestBehaviour;

    /**
     * @var EntityRepository<ProductCollection>
     */
    private EntityRepository $productRepository;

    private AdminSearcher $searcher;

    protected function setUp(): void
    {
        if (!static::getContainer()->getParameter('elasticsearch.administration.enabled')) {
            static::markTestSkipped('No OPENSEARCH configured');
        }

        $this->productRepository = static::getContainer()->get('product.repository');
        $this->searcher = static::getContainer()->get(AdminSearcher::class);

        static::getContainer()->get(Connection::class)->executeStatement('DELETE FROM product');

        $this->clearElasticsearch();
    }

    public function testNonNumericSearchStillWorks(): void
    {
        $ids = new IdsCollection();
        $productLaptopId = $ids->get('TEST-LAPTOP');

        $products = [
            (new ProductBuilder($ids, 'TEST-LAPTOP', 10))
                ->name('Laptop Computer')
                ->price(100)
                ->build(),
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['product']]);
        $this->refreshIndex();

        $results = $this->searcher->search('laptop', ['product'], Context::createDefaultContext());

        static::assertNotEmpty($results);
        static::assertArrayHasKey('product', $results);
        static::assertGreaterThan(0, $results['product']['total']);

        static::assertInstanceOf(ProductCollection::class, $results['product']['data']);
        $foundProductIds = $results['product']['data']->getIds();
        static::assertContains($productLaptopId, $foundProductIds, 'Laptop should be found when searching for "laptop"');
    }

    public function testNumericSearchFindsSubstringMatches(): void
    {
        $ids = new IdsCollection();
        $productX3800Id = $ids->get('TEST-X3800');
        $productABC3800XYZId = $ids->get('TEST-ABC3800XYZ');
        $product3800Id = $ids->get('TEST-3800');

        $products = [
            (new ProductBuilder($ids, 'TEST-X3800', 10))
                ->name('Product X38000')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'TEST-ABC3800XYZ', 10))
                ->name('Product ABC38000XYZ')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'TEST-3800', 10))
                ->name('Product 38000')
                ->price(100)
                ->build(),
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['product']]);
        $this->refreshIndex();

        $results = $this->searcher->search('38000', ['product'], Context::createDefaultContext());

        static::assertNotEmpty($results);
        static::assertArrayHasKey('product', $results);
        static::assertGreaterThanOrEqual(3, $results['product']['total'], 'Should find at least 3 products containing "3800"');

        static::assertInstanceOf(ProductCollection::class, $results['product']['data']);
        $foundProductIds = $results['product']['data']->getIds();

        static::assertContains($productX3800Id, $foundProductIds, 'Product X3800 should be found');
        static::assertContains($productABC3800XYZId, $foundProductIds, 'Product ABC3800XYZ should be found');
        static::assertContains($product3800Id, $foundProductIds, 'Product 3800 should be found');
    }

    public function testNumericSearchDoesNotMatchSimilarNumbers(): void
    {
        $ids = new IdsCollection();
        $product3800Id = $ids->get('TEST-3800');
        $product3000Id = $ids->get('TEST-3000');
        $product3801Id = $ids->get('TEST-3801');

        $products = [
            (new ProductBuilder($ids, 'TEST-3800', 10))
                ->name('Product 3800')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'TEST-3000', 10))
                ->name('Product 3000')
                ->price(100)
                ->build(),
            (new ProductBuilder($ids, 'TEST-3801', 10))
                ->name('Product 3801')
                ->price(100)
                ->build(),
        ];

        $this->productRepository->create($products, Context::createDefaultContext());

        $this->indexElasticSearch(['--only' => ['product']]);
        $this->refreshIndex();

        $results = $this->searcher->search('3800', ['product'], Context::createDefaultContext());

        static::assertNotEmpty($results);
        static::assertArrayHasKey('product', $results);

        static::assertInstanceOf(ProductCollection::class, $results['product']['data']);
        $foundProductIds = $results['product']['data']->getIds();

        static::assertContains($product3800Id, $foundProductIds, 'Product 3800 should be found');

        static::assertNotContains($product3000Id, $foundProductIds, 'Product 3000 should NOT be found (no fuzziness)');

        static::assertNotContains($product3801Id, $foundProductIds, 'Product 3801 should NOT be found (different number)');
    }

    protected function getDiContainer(): ContainerInterface
    {
        return static::getContainer();
    }
}

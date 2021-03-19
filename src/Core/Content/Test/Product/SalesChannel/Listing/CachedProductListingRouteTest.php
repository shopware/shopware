<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Product\SalesChannel\Listing;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\CachedProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Framework\Adapter\Cache\CacheTracer;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

class CachedProductListingRouteTest extends TestCase
{
    use KernelTestBehaviour;

    /**
     * @dataProvider criteriaProvider
     */
    public function testCriteria(Criteria $criteria): void
    {
        $ids = new IdsCollection();

        $context = $this->createMock(SalesChannelContext::class);
        $response = new ProductListingRouteResponse(new ProductListingResult('product', 0, new ProductCollection(), null, $criteria, $context->getContext()));

        $core = $this->createMock(ProductListingRoute::class);
        $core->expects(static::exactly(2))
            ->method('load')
            ->willReturn($response);

        $route = new CachedProductListingRoute(
            $core,
            $this->getContainer()->get('cache.object'),
            $this->getContainer()->get(EntityCacheKeyGenerator::class),
            $this->getContainer()->get(CacheTracer::class),
            $this->getContainer()->get('event_dispatcher'),
            [],
            $this->getContainer()->get('logger')
        );

        $route->load($ids->get('c.1'), new Request(), $context, $criteria);

        $route->load($ids->get('c.1'), new Request(), $context, $criteria);

        $criteria->setLimit(200);

        // check that provided criteria has other key
        $route->load($ids->get('c.1'), new Request(), $context, $criteria);
    }

    public function criteriaProvider(): \Generator
    {
        yield 'Paginated criteria' => [(new Criteria())->setOffset(1)->setLimit(20)];
        yield 'Filtered criteria' => [(new Criteria())->addFilter(new EqualsFilter('active', true))];
        yield 'Post filtered criteria' => [(new Criteria())->addPostFilter(new EqualsFilter('active', true))];
        yield 'Aggregation criteria' => [(new Criteria())->addAggregation(new StatsAggregation('price', 'price'))];
        yield 'Query criteria' => [(new Criteria())->addQuery(new ScoreQuery(new EqualsFilter('active', true), 200))];
        yield 'Term criteria' => [(new Criteria())->setTerm('test')];
        yield 'Sorted criteria' => [(new Criteria())->addSorting(new FieldSorting('active'))];
    }

    /**
     * @dataProvider stateProvider
     */
    public function testStates(array $current, array $config): void
    {
        $ids = new IdsCollection();

        $criteria = new Criteria();

        $hasState = \count(array_intersect($config, $current)) > 0;

        $context = $this->createMock(SalesChannelContext::class);
        $context->expects(static::any())
            ->method('hasState')
            ->willReturn($hasState);

        $response = new ProductListingRouteResponse(new ProductListingResult('product', 0, new ProductCollection(), null, $criteria, $context->getContext()));

        $core = $this->createMock(ProductListingRoute::class);

        $calls = 1;
        if ($hasState) {
            $calls = 2;
        }
        $core->expects(static::exactly($calls))
            ->method('load')
            ->willReturn($response);

        $route = new CachedProductListingRoute(
            $core,
            $this->getContainer()->get('cache.object'),
            $this->getContainer()->get(EntityCacheKeyGenerator::class),
            $this->getContainer()->get(CacheTracer::class),
            $this->getContainer()->get('event_dispatcher'),
            $config,
            $this->getContainer()->get('logger')
        );

        $route->load($ids->get('c.1'), new Request(), $context, $criteria);

        $route->load($ids->get('c.1'), new Request(), $context, $criteria);
    }

    public function stateProvider(): \Generator
    {
        yield 'No states' => [[], []];
        yield 'Has one state' => [['logged-in'], ['logged-in', 'cart-filled']];
        yield 'Has no state' => [['logged-in'], ['cart-filled']];
        yield 'Has multiple states' => [['logged-in', 'cart-filled'], ['logged-in', 'cart-filled']];
    }
}

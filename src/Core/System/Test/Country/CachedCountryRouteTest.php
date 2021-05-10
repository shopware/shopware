<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Country;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheTracer;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\StatsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Query\ScoreQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\SalesChannel\CachedCountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryRouteResponse;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group cache
 * @group store-api
 */
class CachedCountryRouteTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    /**
     * @afterClass
     */
    public function cleanup(): void
    {
        $this->getContainer()->get('cache.object')
            ->invalidateTags([CachedCountryRoute::buildName(Defaults::SALES_CHANNEL)]);
    }

    /**
     * @dataProvider criteriaProvider
     */
    public function testCriteria(Criteria $criteria): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $response = new CountryRouteResponse(
            new EntitySearchResult('country', 0, new CountryCollection(), null, $criteria, $context->getContext())
        );

        $core = $this->createMock(CountryRoute::class);
        $core->expects(static::exactly(2))
            ->method('load')
            ->willReturn($response);

        $route = new CachedCountryRoute(
            $core,
            new TagAwareAdapter(new ArrayAdapter(100)),
            $this->getContainer()->get(EntityCacheKeyGenerator::class),
            $this->getContainer()->get(CacheTracer::class),
            $this->getContainer()->get('event_dispatcher'),
            [],
            $this->getContainer()->get('logger')
        );

        $route->load(new Request(), $criteria, $context);

        $route->load(new Request(), $criteria, $context);

        $criteria->setLimit(200);

        // check that provided criteria has other key
        $route->load(new Request(), $criteria, $context);
    }

    public function criteriaProvider(): \Generator
    {
        yield 'Paginated criteria' => [(new Criteria())->setOffset(1)->setLimit(20)];
        yield 'Filtered criteria' => [(new Criteria())->addFilter(new EqualsFilter('active', true))];
        yield 'Post filtered criteria' => [(new Criteria())->addPostFilter(new EqualsFilter('active', true))];
        yield 'Aggregation criteria' => [(new Criteria())->addAggregation(new StatsAggregation('name', 'name'))];
        yield 'Query criteria' => [(new Criteria())->addQuery(new ScoreQuery(new EqualsFilter('active', true), 200))];
        yield 'Term criteria' => [(new Criteria())->setTerm('test')];
        yield 'Sorted criteria' => [(new Criteria())->addSorting(new FieldSorting('active'))];
    }

    /**
     * @dataProvider invalidationProvider
     */
    public function testInvalidation(\Closure $before, \Closure $after, int $calls): void
    {
        $this->getContainer()->get('cache.object')
            ->invalidateTags([CachedCountryRoute::buildName(Defaults::SALES_CHANNEL)]);

        $route = $this->getContainer()->get(CountryRoute::class);

        static::assertInstanceOf(CachedCountryRoute::class, $route);

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();

        $listener->expects(static::exactly($calls))->method('__invoke');
        $dispatcher->addListener('country.loaded', $listener);

        $before();

        $route->load(new Request(), new Criteria(), $this->context);
        $route->load(new Request(), new Criteria(), $this->context);

        $after();

        $route->load(new Request(), new Criteria(), $this->context);
        $route->load(new Request(), new Criteria(), $this->context);
    }

    public function invalidationProvider()
    {
        $ids = new IdsCollection();

        yield 'Cache not invalidated if country not assigned' => [
            function (): void {
            },
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                    'name' => 'test',
                ];

                $this->getContainer()->get('country.repository')->create([$data], $ids->getContext());
            },
            1,
        ];

        yield 'Cache invalidated if created country assigned' => [
            function (): void {
            },
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                    'name' => 'test',
                    'salesChannels' => [['id' => Defaults::SALES_CHANNEL]],
                ];

                $this->getContainer()->get('country.repository')->create([$data], $ids->getContext());
            },
            2,
        ];

        yield 'Cache not invalidated if updated country not assigned' => [
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                    'name' => 'test',
                ];

                $this->getContainer()->get('country.repository')->create([$data], $ids->getContext());
            },
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                    'name' => 'update',
                ];

                $this->getContainer()->get('country.repository')->update([$data], $ids->getContext());
            },
            1,
        ];

        yield 'Cache invalidated if updated country assigned' => [
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                    'name' => 'test',
                    'salesChannels' => [['id' => Defaults::SALES_CHANNEL]],
                ];

                $this->getContainer()->get('country.repository')->create([$data], $ids->getContext());
            },
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                    'name' => 'update',
                ];

                $this->getContainer()->get('country.repository')->update([$data], $ids->getContext());
            },
            2,
        ];

        yield 'Cache invalidated if deleted country not assigned' => [
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                    'name' => 'test',
                ];

                $this->getContainer()->get('country.repository')->create([$data], $ids->getContext());
            },
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                ];

                $this->getContainer()->get('country.repository')->delete([$data], $ids->getContext());
            },
            2,
        ];

        yield 'Cache invalidated if deleted country assigned' => [
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                    'name' => 'test',
                    'salesChannels' => [['id' => Defaults::SALES_CHANNEL]],
                ];

                $this->getContainer()->get('country.repository')->create([$data], $ids->getContext());
            },
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                ];

                $this->getContainer()->get('country.repository')->delete([$data], $ids->getContext());
            },
            2,
        ];

        yield 'Cache invalidated when country assigned' => [
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                    'name' => 'test',
                ];

                $this->getContainer()->get('country.repository')->create([$data], $ids->getContext());
            },
            function () use ($ids): void {
                $data = [
                    'countryId' => $ids->get('country'),
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                ];

                $this->getContainer()->get('sales_channel_country.repository')
                    ->create([$data], $ids->getContext());
            },
            2,
        ];

        yield 'Cache invalidated when delete country assignment' => [
            function () use ($ids): void {
                $data = [
                    'id' => $ids->get('country'),
                    'name' => 'test',
                    'salesChannels' => [['id' => Defaults::SALES_CHANNEL]],
                ];

                $this->getContainer()->get('country.repository')->create([$data], $ids->getContext());
            },
            function () use ($ids): void {
                $data = [
                    'countryId' => $ids->get('country'),
                    'salesChannelId' => Defaults::SALES_CHANNEL,
                ];

                $this->getContainer()->get('sales_channel_country.repository')
                    ->delete([$data], $ids->getContext());
            },
            2,
        ];
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Salutation;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Cache\CacheTracer;
use Shopware\Core\Framework\Context;
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
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\Salutation\SalesChannel\CachedSalutationRoute;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRoute;
use Shopware\Core\System\Salutation\SalesChannel\SalutationRouteResponse;
use Shopware\Core\System\Salutation\SalutationCollection;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @group cache
 * @group store-api
 */
class CachedSalutationRouteTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);
    }

    /**
     * @afterClass
     */
    public function cleanup(): void
    {
        $this->getContainer()->get('cache.object')
            ->invalidateTags([CachedSalutationRoute::ALL_TAG]);
    }

    /**
     * @dataProvider criteriaProvider
     */
    public function testCriteria(Criteria $criteria): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $response = new SalutationRouteResponse(
            new EntitySearchResult('salutation', 0, new SalutationCollection(), null, $criteria, $context->getContext())
        );

        $core = $this->createMock(SalutationRoute::class);
        $core->expects(static::exactly(2))
            ->method('load')
            ->willReturn($response);

        $route = new CachedSalutationRoute(
            $core,
            new TagAwareAdapter(new ArrayAdapter(100)),
            $this->getContainer()->get(EntityCacheKeyGenerator::class),
            $this->getContainer()->get(CacheTracer::class),
            $this->getContainer()->get('event_dispatcher'),
            [],
            $this->getContainer()->get('logger')
        );

        $route->load(new Request(), $context, $criteria);

        $route->load(new Request(), $context, $criteria);

        $criteria->setLimit(200);

        // check that provided criteria has other key
        $route->load(new Request(), $context, $criteria);
    }

    public static function criteriaProvider(): \Generator
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
            ->invalidateTags([CachedSalutationRoute::ALL_TAG]);

        $route = $this->getContainer()->get(SalutationRoute::class);

        static::assertInstanceOf(CachedSalutationRoute::class, $route);

        $dispatcher = $this->getContainer()->get('event_dispatcher');
        $listener = $this->getMockBuilder(CallableClass::class)->getMock();

        $listener->expects(static::exactly($calls))->method('__invoke');
        $this->addEventListener($dispatcher, 'salutation.loaded', $listener);

        $before($this->getContainer());

        $route->load(new Request(), $this->context, new Criteria());
        $route->load(new Request(), $this->context, new Criteria());

        $after($this->getContainer());

        $route->load(new Request(), $this->context, new Criteria());
        $route->load(new Request(), $this->context, new Criteria());
    }

    public static function invalidationProvider()
    {
        $ids = new IdsCollection();

        yield 'Cache invalidated if created salutation assigned' => [
            function (): void {
            },
            function (ContainerInterface $container) use ($ids): void {
                $data = [
                    'id' => $ids->get('salutation'),
                    'displayName' => 'test',
                    'letterName' => 'test',
                    'salutationKey' => 'test',
                ];

                $container->get('salutation.repository')->create([$data], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache invalidated if updated salutation assigned' => [
            function (ContainerInterface $container) use ($ids): void {
                $data = [
                    'id' => $ids->get('salutation'),
                    'displayName' => 'test',
                    'letterName' => 'test',
                    'salutationKey' => 'test',
                ];

                $container->get('salutation.repository')->create([$data], Context::createDefaultContext());
            },
            function (ContainerInterface $container) use ($ids): void {
                $data = [
                    'id' => $ids->get('salutation'),
                    'displayName' => 'update',
                ];

                $container->get('salutation.repository')->update([$data], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache invalidated if deleted salutation assigned' => [
            function (ContainerInterface $container) use ($ids): void {
                $data = [
                    'id' => $ids->get('salutation'),
                    'displayName' => 'test',
                    'letterName' => 'test',
                    'salutationKey' => 'test',
                ];

                $container->get('salutation.repository')->create([$data], Context::createDefaultContext());
            },
            function (ContainerInterface $container) use ($ids): void {
                $data = [
                    'id' => $ids->get('salutation'),
                ];

                $container->get('salutation.repository')->delete([$data], Context::createDefaultContext());
            },
            2,
        ];
    }
}

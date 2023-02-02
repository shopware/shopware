<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Country\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\Event\CountryStateRouteCacheTagsEvent;
use Shopware\Core\System\Country\SalesChannel\CachedCountryStateRoute;
use Shopware\Core\System\Country\SalesChannel\CountryStateRoute;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 * @group cache
 * @group store-api
 */
class CachedCountryStateRouteTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private const ALL_TAG = 'test-tag';

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $ids = new IdsCollection();
        $this->getContainer()->get('country.repository')->create(
            [
                [
                    'id' => $ids->get('country'),
                    'name' => 'testCountry',
                ],
                [
                    'id' => $ids->get('other_country'),
                    'name' => 'otherCountry',
                ],
            ],
            Context::createDefaultContext()
        );
    }

    /**
     * @afterClass
     */
    public function cleanup(): void
    {
        $this->getContainer()->get('cache.object')
            ->invalidateTags([self::ALL_TAG]);
    }

    /**
     * @dataProvider invalidationProvider
     */
    public function testInvalidation(string $countryId, \Closure $before, \Closure $after, int $calls): void
    {
        $this->getContainer()->get('cache.object')->invalidateTags([self::ALL_TAG]);

        $this->getContainer()->get('event_dispatcher')
            ->addListener(CountryStateRouteCacheTagsEvent::class, static function (CountryStateRouteCacheTagsEvent $event): void {
                $event->addTags([self::ALL_TAG]);
            });

        $route = $this->getContainer()->get(CountryStateRoute::class);
        static::assertInstanceOf(CachedCountryStateRoute::class, $route);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly($calls))->method('__invoke');

        $this->getContainer()
            ->get('event_dispatcher')
            ->addListener(CountryStateRouteCacheTagsEvent::class, $listener);

        $before();

        $route->load($countryId, new Request(), new Criteria(), $this->context);
        $route->load($countryId, new Request(), new Criteria(), $this->context);

        $after();

        $route->load($countryId, new Request(), new Criteria(), $this->context);
        $route->load($countryId, new Request(), new Criteria(), $this->context);
    }

    public function invalidationProvider(): \Generator
    {
        $ids = new IdsCollection();
        $this->getContainer()->get('country.repository')->create(
            [
                [
                    'id' => $ids->get('country'),
                    'name' => 'testCountry',
                ],
                [
                    'id' => $ids->get('other_country'),
                    'name' => 'otherCountry',
                ],
            ],
            Context::createDefaultContext()
        );

        $states = [
            [
                'id' => $ids->get('stateId1'),
                'name' => 'test1',
                'countryId' => $ids->get('country'),
                'shortCode' => 'test1',
                'active' => true,
                'position' => 0,
            ],
            [
                'id' => $ids->get('stateId2'),
                'name' => 'test2',
                'countryId' => $ids->get('country'),
                'shortCode' => 'test2',
                'active' => true,
                'position' => 1,
            ],
            [
                'id' => $ids->get('stateId3'),
                'name' => 'test3',
                'countryId' => $ids->get('country'),
                'shortCode' => 'test3',
                'active' => false,
                'position' => 3,
            ],
            [
                'id' => $ids->get('stateId4'),
                'name' => 'test4',
                'countryId' => $ids->get('country'),
                'shortCode' => 'test4',
                'active' => true,
                'position' => 4,
            ],
        ];

        yield 'Cache gets invalidated, if state is updated' => [
            $ids->get('country'),
            function () use ($states): void {
                $this->getContainer()->get('country_state.repository')->create($states, Context::createDefaultContext());
            },
            function () use ($ids): void {
                $state = ['id' => $ids->get('stateId1'), 'name' => 'test00'];
                $this->getContainer()->get('country_state.repository')->update([$state], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if country gets active' => [
            $ids->get('country'),
            function () use ($states): void {
                $this->getContainer()->get('country_state.repository')->create($states, Context::createDefaultContext());
            },
            function () use ($ids): void {
                $state = ['id' => $ids->get('stateId3'), 'active' => true];
                $this->getContainer()->get('country_state.repository')->update([$state], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if country gets deleted' => [
            $ids->get('country'),
            function () use ($states): void {
                $this->getContainer()->get('country_state.repository')->create($states, Context::createDefaultContext());
            },
            function () use ($ids): void {
                $delete = ['id' => $ids->get('stateId2')];
                $this->getContainer()->get('country_state.repository')->delete([$delete], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if state gets reassigned' => [
            $ids->get('country'),
            function () use ($states): void {
                $this->getContainer()->get('country_state.repository')->create($states, Context::createDefaultContext());
            },
            function () use ($ids): void {
                $update = ['id' => $ids->get('stateId2'), 'countryId' => $ids->get('other_country')];
                $this->getContainer()->get('country_state.repository')->update([$update], Context::createDefaultContext());
            },
            2,
        ];
    }
}

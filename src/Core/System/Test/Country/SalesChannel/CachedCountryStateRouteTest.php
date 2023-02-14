<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Country\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
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
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @group cache
 * @group store-api
 */
#[Package('system-settings')]
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

        $countryId = $ids->get($countryId);

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

        $before($this->getContainer(), $ids);

        $route->load($countryId, new Request(), new Criteria(), $this->context);
        $route->load($countryId, new Request(), new Criteria(), $this->context);

        $after($this->getContainer(), $ids);

        $route->load($countryId, new Request(), new Criteria(), $this->context);
        $route->load($countryId, new Request(), new Criteria(), $this->context);
    }

    public static function invalidationProvider(): \Generator
    {
        yield 'Cache gets invalidated, if state is updated' => [
            'country',
            function (ContainerInterface $container, IdsCollection $ids): void {
                $container->get('country_state.repository')->create(self::getStates($ids), Context::createDefaultContext());
            },
            function (ContainerInterface $container, IdsCollection $ids): void {
                $state = ['id' => $ids->get('stateId1'), 'name' => 'test00'];
                $container->get('country_state.repository')->update([$state], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if country gets active' => [
            'country',
            function (ContainerInterface $container, IdsCollection $ids): void {
                $container->get('country_state.repository')->create(self::getStates($ids), Context::createDefaultContext());
            },
            function (ContainerInterface $container, IdsCollection $ids): void {
                $state = ['id' => $ids->get('stateId3'), 'active' => true];
                $container->get('country_state.repository')->update([$state], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if country gets deleted' => [
            'country',
            function (ContainerInterface $container, IdsCollection $ids): void {
                $container->get('country_state.repository')->create(self::getStates($ids), Context::createDefaultContext());
            },
            function (ContainerInterface $container, IdsCollection $ids): void {
                $delete = ['id' => $ids->get('stateId2')];
                $container->get('country_state.repository')->delete([$delete], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if state gets reassigned' => [
            'country',
            function (ContainerInterface $container, IdsCollection $ids): void {
                $container->get('country_state.repository')->create(self::getStates($ids), Context::createDefaultContext());
            },
            function (ContainerInterface $container, IdsCollection $ids): void {
                $update = ['id' => $ids->get('stateId2'), 'countryId' => $ids->get('other_country')];
                $container->get('country_state.repository')->update([$update], Context::createDefaultContext());
            },
            2,
        ];
    }

    /**
     * @return array<mixed>
     */
    private static function getStates(IdsCollection $ids): array
    {
        return [
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
    }
}

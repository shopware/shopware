<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Category\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Event\NavigationRouteCacheTagsEvent;
use Shopware\Core\Content\Category\SalesChannel\NavigationRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @group cache
 * @group store-api
 */
class CachedNavigationRouteTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const ALL_TAG = 'test-tag';

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        parent::setUp();

        $this->context = $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);
    }

    /**
     * @dataProvider invalidationProvider
     */
    public function testInvalidation(IdsCollection $ids, int $depth, \Closure $before, \Closure $after, int $calls): void
    {
        // to improve performance, we generate the required data one time and test different case with same data set
        $this->initData($ids);

        $this->getContainer()->get('cache.object')->invalidateTags([self::ALL_TAG]);

        $this->getContainer()->get('event_dispatcher')
            ->addListener(NavigationRouteCacheTagsEvent::class, static function (NavigationRouteCacheTagsEvent $event): void {
                $event->addTags([self::ALL_TAG]);
            });

        $route = $this->getContainer()->get(NavigationRoute::class);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly($calls))->method('__invoke');

        $this->getContainer()
            ->get('event_dispatcher')
            ->addListener(NavigationRouteCacheTagsEvent::class, $listener);

        $context = $this->context;
        $root = $context->getSalesChannel()->getNavigationCategoryId();

        $id = $before($ids, $context);

        $route->load($id, $root, self::request($depth), $context, new Criteria());
        $route->load($id, $root, self::request($depth), $context, new Criteria());

        $after($ids, $context);

        $route->load($id, $root, self::request($depth), $context, new Criteria());
        $response = $route->load($id, $root, self::request($depth), $context, new Criteria());

        static::assertTrue($response->getCategories()->has($id));
        static::assertTrue($response->getCategories()->count() > 0);
    }

    public function invalidationProvider()
    {
        $ids = new IdsCollection();

        yield 'Test root call' => [
            $ids,
            2,
            function (IdsCollection $ids): string {
                return $ids->get('navigation');
            },
            function (IdsCollection $ids): void {
            },
            1,
        ];

        yield 'Test when active inside base navigation' => [
            $ids,
            3,
            function (IdsCollection $ids): string {
                return $ids->get('cat-1.1.1');
            },
            function (IdsCollection $ids): void {
            },
            1,
        ];

        yield 'Test when active outside base navigation' => [
            $ids,
            1,
            function (IdsCollection $ids): string {
                return $ids->get('cat-1.1.1');
            },
            function (IdsCollection $ids): void {
            },
            2,
        ];

        yield 'Test invalidated if category disabled' => [
            $ids,
            1,
            function (IdsCollection $ids): string {
                return $ids->get('cat-1.1.1');
            },
            function (IdsCollection $ids): void {
                $this->getContainer()->get('category.repository')->update([
                    ['id' => $ids->get('cat-1.2.0'), 'active' => false],
                ], Context::createDefaultContext());
            },
            3,
        ];

        yield 'Test invalidated if category deleted' => [
            $ids,
            1,
            function (IdsCollection $ids): string {
                return $ids->get('cat-1.1.1');
            },
            function (IdsCollection $ids): void {
                $this->getContainer()->get('category.repository')->delete([
                    ['id' => $ids->get('cat-1.2.2')],
                ], Context::createDefaultContext());
            },
            3,
        ];

        yield 'Test invalidated if category created' => [
            $ids,
            1,
            function (IdsCollection $ids): string {
                return $ids->get('cat-1.1.1');
            },
            function (IdsCollection $ids): void {
                $this->getContainer()->get('category.repository')->create([
                    ['id' => $ids->get('cat-1.2.4'), 'name' => 'cat 1.2.4', 'active' => true],
                ], Context::createDefaultContext());
            },
            3,
        ];
    }

    private static function request(int $depth): Request
    {
        $request = new Request();
        $request->query->set('depth', $depth);
        $request->query->set('buildTree', false);

        return $request;
    }

    private function initData(IdsCollection $ids): void
    {
        $context = $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $ids->set('navigation', $context->getSalesChannel()->getNavigationCategoryId());

        $this->context = $this->getContainer()
            ->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), Defaults::SALES_CHANNEL);

        $categories = [
            ['id' => $ids->get('cat-1.0.0'), 'parentId' => $ids->get('navigation'), 'name' => 'cat 1.0.0', 'active' => true, 'children' => [
                ['id' => $ids->get('cat-1.1.0'), 'name' => 'cat 1.1.0', 'active' => true, 'children' => [
                    ['id' => $ids->get('cat-1.1.1'), 'name' => 'cat 1.1.1', 'active' => true],
                    ['id' => $ids->get('cat-1.1.2'), 'name' => 'cat 1.1.2', 'active' => true],
                    ['id' => $ids->get('cat-1.1.3'), 'name' => 'cat 1.1.3', 'active' => true],
                ]],
                ['id' => $ids->get('cat-1.2.0'), 'name' => 'cat 1.2.0', 'active' => true, 'children' => [
                    ['id' => $ids->get('cat-1.2.1'), 'name' => 'cat 1.2.1', 'active' => true],
                    ['id' => $ids->get('cat-1.2.2'), 'name' => 'cat 1.2.2', 'active' => true],
                    ['id' => $ids->get('cat-1.2.3'), 'name' => 'cat 1.2.3', 'active' => true],
                ]],
            ]],
            ['id' => $ids->get('cat-2.0.0'), 'parentId' => $ids->get('navigation'), 'name' => 'cat 2.0.0', 'active' => true, 'children' => [
                ['id' => $ids->get('cat-2.1.0'), 'name' => 'cat 2.1.0', 'active' => true],
                ['id' => $ids->get('cat-2.2.0'), 'name' => 'cat 2.2.0', 'active' => true],
            ]],
        ];

        $this->getContainer()->get('category.repository')->create($categories, Context::createDefaultContext());
    }
}

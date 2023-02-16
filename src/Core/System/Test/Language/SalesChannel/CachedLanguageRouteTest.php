<?php declare(strict_types=1);

namespace Shopware\Core\System\Test\Language\SalesChannel;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Language\Event\LanguageRouteCacheTagsEvent;
use Shopware\Core\System\Language\SalesChannel\CachedLanguageRoute;
use Shopware\Core\System\Language\SalesChannel\LanguageRoute;
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
class CachedLanguageRouteTest extends TestCase
{
    use KernelTestBehaviour;
    use DatabaseTransactionBehaviour;

    private const ALL_TAG = 'test-tag';

    private const LANGUAGE = [
        'name' => 'test',
        'parentId' => Defaults::LANGUAGE_SYSTEM,
        'locale' => ['code' => 'test', 'territory' => 'test', 'name' => 'test'],
    ];

    private const ASSIGNED = [
        'salesChannels' => [['id' => TestDefaults::SALES_CHANNEL]],
    ];

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
            ->invalidateTags([self::ALL_TAG]);
    }

    /**
     * @dataProvider invalidationProvider
     */
    public function testInvalidation(\Closure $before, \Closure $after, int $calls): void
    {
        $this->getContainer()->get('cache.object')->invalidateTags([self::ALL_TAG]);

        $this->getContainer()->get('event_dispatcher')
            ->addListener(LanguageRouteCacheTagsEvent::class, static function (LanguageRouteCacheTagsEvent $event): void {
                $event->addTags([self::ALL_TAG]);
            });

        $route = $this->getContainer()->get(LanguageRoute::class);
        static::assertInstanceOf(CachedLanguageRoute::class, $route);

        $listener = $this->getMockBuilder(CallableClass::class)->getMock();
        $listener->expects(static::exactly($calls))->method('__invoke');

        $this->getContainer()
            ->get('event_dispatcher')
            ->addListener(LanguageRouteCacheTagsEvent::class, $listener);

        $before($this->getContainer());

        $route->load(new Request(), $this->context, new Criteria());
        $route->load(new Request(), $this->context, new Criteria());

        $after($this->getContainer());

        $route->load(new Request(), $this->context, new Criteria());
        $route->load(new Request(), $this->context, new Criteria());
    }

    public static function invalidationProvider(): \Generator
    {
        $ids = new IdsCollection();

        yield 'Cache gets invalidated, if created language assigned to the sales channel' => [
            function (): void {
            },
            function (ContainerInterface $container) use ($ids): void {
                $language = array_merge(self::LANGUAGE, self::ASSIGNED, ['id' => $ids->get('language')]);
                $container->get('language.repository')->create([$language], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if updated language assigned to the sales channel' => [
            function (ContainerInterface $container) use ($ids): void {
                $language = array_merge(self::LANGUAGE, self::ASSIGNED, ['id' => $ids->get('language')]);
                $container->get('language.repository')->create([$language], Context::createDefaultContext());
            },
            function (ContainerInterface $container) use ($ids): void {
                $update = ['id' => $ids->get('language'), 'name' => 'update'];
                $container->get('language.repository')->update([$update], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets invalidated, if deleted language assigned to the sales channel' => [
            function (ContainerInterface $container) use ($ids): void {
                $language = array_merge(self::LANGUAGE, self::ASSIGNED, ['id' => $ids->get('language')]);
                $container->get('language.repository')->create([$language], Context::createDefaultContext());
            },
            function (ContainerInterface $container) use ($ids): void {
                $delete = ['id' => $ids->get('language')];
                $container->get('language.repository')->delete([$delete], Context::createDefaultContext());
            },
            2,
        ];

        yield 'Cache gets not invalidated, if created language not assigned to the sales channel' => [
            function (): void {
            },
            function (ContainerInterface $container) use ($ids): void {
                $language = array_merge(self::LANGUAGE, ['id' => $ids->get('language')]);
                $container->get('language.repository')->create([$language], Context::createDefaultContext());
            },
            1,
        ];

        yield 'Cache gets not invalidated, if updated language not assigned to the sales channel' => [
            function (ContainerInterface $container) use ($ids): void {
                $language = array_merge(self::LANGUAGE, ['id' => $ids->get('language')]);
                $container->get('language.repository')->create([$language], Context::createDefaultContext());
            },
            function (ContainerInterface $container) use ($ids): void {
                $update = ['id' => $ids->get('language'), 'name' => 'update'];
                $container->get('language.repository')->update([$update], Context::createDefaultContext());
            },
            1,
        ];

        yield 'Cache gets invalidated, if deleted language is not assigned to the sales channel' => [
            function (ContainerInterface $container) use ($ids): void {
                $language = array_merge(self::LANGUAGE, ['id' => $ids->get('language')]);
                $container->get('language.repository')->create([$language], Context::createDefaultContext());
            },
            function (ContainerInterface $container) use ($ids): void {
                $delete = ['id' => $ids->get('language')];
                $container->get('language.repository')->delete([$delete], Context::createDefaultContext());
            },
            2,
        ];
    }
}

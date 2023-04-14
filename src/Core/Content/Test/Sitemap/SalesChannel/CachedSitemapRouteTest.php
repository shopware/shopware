<?php declare(strict_types=1);

namespace Shopware\Core\Content\Test\Sitemap\SalesChannel;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\SalesChannel\AbstractSitemapRoute;
use Shopware\Core\Content\Sitemap\SalesChannel\CachedSitemapRoute;
use Shopware\Core\Content\Sitemap\SalesChannel\SitemapRouteResponse;
use Shopware\Core\Content\Sitemap\Service\SitemapExporter;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheTracer;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\DatabaseTransactionBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextFactory;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\TestDefaults;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @group cache
 * @group store-api
 */
#[Package('sales-channel')]
class CachedSitemapRouteTest extends TestCase
{
    use KernelTestBehaviour;

    use DatabaseTransactionBehaviour;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        if (!$this->getContainer()->has(ProductPageSeoUrlRoute::class)) {
            static::markTestSkipped('NEXT-16799: Sitemap module has a dependency on storefront routes');
        }
        parent::setUp();
    }

    /**
     * @afterClass
     */
    public function cleanup(): void
    {
        $this->getContainer()->get('cache.object')
            ->invalidateTags([CachedSitemapRoute::ALL_TAG]);
    }

    /**
     * @dataProvider invalidationProvider
     */
    public function testInvalidation(\Closure $before, \Closure $after, int $calls, int $strategy = SitemapExporterInterface::STRATEGY_SCHEDULED_TASK): void
    {
        $this->getContainer()->get('cache.object')
            ->invalidateTags([CachedSitemapRoute::ALL_TAG]);

        $ids = new IdsCollection();

        $snippetSetId = $this->getContainer()->get(Connection::class)
            ->fetchOne('SELECT LOWER(HEX(id)) FROM snippet_set LIMIT 1');

        $domain = [
            'url' => 'http://shopware.test',
            'salesChannelId' => TestDefaults::SALES_CHANNEL,
            'languageId' => Defaults::LANGUAGE_SYSTEM,
            'currencyId' => Defaults::CURRENCY,
            'snippetSetId' => $snippetSetId,
        ];

        $this->getContainer()->get('sales_channel_domain.repository')
            ->create([$domain], Context::createDefaultContext());

        $this->context = $this->getContainer()->get(SalesChannelContextFactory::class)
            ->create(Uuid::randomHex(), TestDefaults::SALES_CHANNEL);

        $products = [
            (new ProductBuilder($ids, 'first'))
                ->price(100)
                ->visibility()
                ->build(),
            (new ProductBuilder($ids, 'second'))
                ->price(100)
                ->visibility()
                ->build(),
        ];

        $this->getContainer()->get('product.repository')
            ->create($products, Context::createDefaultContext());

        $counter = new SitemapRouteCounter(
            $this->getContainer()->get('Shopware\Core\Content\Sitemap\SalesChannel\CachedSitemapRoute.inner')
        );

        $config = $this->createMock(SystemConfigService::class);
        $config->expects(static::any())
            ->method('getInt')
            ->with('core.sitemap.sitemapRefreshStrategy')
            ->willReturn($strategy);

        $route = new CachedSitemapRoute(
            $counter,
            $this->getContainer()->get('cache.object'),
            $this->getContainer()->get(EntityCacheKeyGenerator::class),
            $this->getContainer()->get(CacheTracer::class),
            $this->getContainer()->get('event_dispatcher'),
            [],
            $config
        );

        $before($this->context, $this->getContainer());

        $route->load(new Request(), $this->context);
        $route->load(new Request(), $this->context);

        $after($this->context, $this->getContainer());

        $route->load(new Request(), $this->context);
        $route->load(new Request(), $this->context);

        static::assertSame($calls, $counter->count);
    }

    public static function invalidationProvider(): \Generator
    {
        yield 'Cache invalidated if sitemap generated' => [
            function (): void {
            },
            function (SalesChannelContext $context, ContainerInterface $container): void {
                $container->get(SitemapExporter::class)->generate($context, true);
            },
            2,
        ];

        yield 'Sitemap not cached for live strategy' => [
            function (): void {
            },
            function (): void {
            },
            4,
            SitemapExporterInterface::STRATEGY_LIVE,
        ];
    }
}

/**
 * @internal
 */
class SitemapRouteCounter extends AbstractSitemapRoute
{
    public int $count = 0;

    public function __construct(private readonly AbstractSitemapRoute $decorated)
    {
    }

    public function load(Request $request, SalesChannelContext $context): SitemapRouteResponse
    {
        ++$this->count;

        return $this->getDecorated()->load($request, $context);
    }

    public function getDecorated(): AbstractSitemapRoute
    {
        return $this->decorated;
    }
}

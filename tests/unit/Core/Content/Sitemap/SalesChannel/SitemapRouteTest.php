<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Extension\SitemapRouteExtension;
use Shopware\Core\Content\Sitemap\SalesChannel\SitemapRoute;
use Shopware\Core\Content\Sitemap\SalesChannel\SitemapRouteResponse;
use Shopware\Core\Content\Sitemap\Service\SitemapExporterInterface;
use Shopware\Core\Content\Sitemap\Service\SitemapListerInterface;
use Shopware\Core\Content\Sitemap\Struct\SitemapCollection;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SitemapRoute::class)]
class SitemapRouteTest extends TestCase
{
    public function testPublishesExtension(): void
    {
        $request = new Request();
        $context = Generator::generateSalesChannelContext();
        $response = new SitemapRouteResponse(new SitemapCollection());

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('sitemap-route.load.pre', static function (SitemapRouteExtension $extension) use ($request, $context, $response): void {
            static::assertSame(['request' => $request, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new SitemapRoute(
            static::createStub(SitemapListerInterface::class),
            static::createStub(SystemConfigService::class),
            static::createStub(SitemapExporterInterface::class),
            static::createStub(CacheTagCollector::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($request, $context));
    }
}

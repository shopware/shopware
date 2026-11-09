<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\App\AppEntitySeoUrlConfig;
use Shopware\Storefront\Framework\Seo\App\AppSeoUrlRoute;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(AppSeoUrlRoute::class)]
class AppSeoUrlRouteTest extends TestCase
{
    public function testConfigGeneratesPathsThroughTheScriptEndpointOfTheDeclaredHook(): void
    {
        $definition = new ProductDefinition();

        $config = (new AppSeoUrlRoute($definition, $this->seoUrl()))->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame('storefront.app.SwagSeoUrlApp.product-teaser', $config->getRouteName());
        static::assertSame('frontend.script_endpoint', $config->getTargetRouteName());
        static::assertSame(AppSeoUrlRoute::TARGET_ROUTE, $config->getTargetRouteName());
        static::assertSame('teaser/{{ product.productNumber }}', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
    }

    public function testPrimaryKeyIsPassedAsIdNextToTheHookParameter(): void
    {
        $route = new AppSeoUrlRoute(new ProductDefinition(), $this->seoUrl());

        static::assertSame(
            ['hook' => 'teaser-page', 'id' => 'c1a5b1a3c1a5b1a3c1a5b1a3c1a5b1a3'],
            $route->getConfig()->getPrimaryKeyParameter('c1a5b1a3c1a5b1a3c1a5b1a3c1a5b1a3')
        );
    }

    public function testPrepareCriteriaLeavesTheCriteriaUntouched(): void
    {
        $route = new AppSeoUrlRoute(new ProductDefinition(), $this->seoUrl());

        $criteria = new Criteria();
        $route->prepareCriteria($criteria, new SalesChannelEntity());

        static::assertEquals(new Criteria(), $criteria);
    }

    public function testRouteNamesAreNamespacedByTheDeclaringApp(): void
    {
        static::assertSame('storefront.app.SwagSeoUrlApp.', AppSeoUrlRoute::routeNamePrefix('SwagSeoUrlApp'));
        static::assertSame('storefront.app.SwagSeoUrlApp.imprint', AppSeoUrlRoute::buildRouteName('SwagSeoUrlApp', 'imprint'));
    }

    private function seoUrl(): AppEntitySeoUrlConfig
    {
        return new AppEntitySeoUrlConfig(
            name: 'product-teaser',
            routeName: 'storefront.app.SwagSeoUrlApp.product-teaser',
            hook: 'teaser-page',
            entityName: 'product',
            defaultTemplate: 'teaser/{{ product.productNumber }}',
        );
    }
}

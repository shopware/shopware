<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo\App;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
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
        $definition = static::createStub(ProductDefinition::class);

        $route = new AppSeoUrlRoute(
            $definition,
            'storefront.app.SwagSeoUrlApp.product-teaser',
            'product-teaser',
            '{{ product.translated.name }}'
        );

        $config = $route->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame('storefront.app.SwagSeoUrlApp.product-teaser', $config->getRouteName());
        static::assertSame('frontend.script_endpoint', $config->getTargetRouteName());
        static::assertSame(AppSeoUrlRoute::TARGET_ROUTE, $config->getTargetRouteName());
        static::assertSame('{{ product.translated.name }}', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
    }

    public function testPrimaryKeyIsPassedAsIdNextToTheHookParameter(): void
    {
        $route = new AppSeoUrlRoute(
            static::createStub(ProductDefinition::class),
            'storefront.app.SwagSeoUrlApp.product-teaser',
            'product-teaser',
            '{{ product.translated.name }}'
        );

        static::assertSame(
            ['hook' => 'product-teaser', 'id' => 'c1a5b1a3c1a5b1a3c1a5b1a3c1a5b1a3'],
            $route->getConfig()->getPrimaryKeyParameter('c1a5b1a3c1a5b1a3c1a5b1a3c1a5b1a3')
        );
    }

    public function testPrepareCriteriaLeavesTheCriteriaUntouched(): void
    {
        $route = new AppSeoUrlRoute(
            static::createStub(ProductDefinition::class),
            'storefront.app.SwagSeoUrlApp.product-teaser',
            'product-teaser',
            '{{ product.translated.name }}'
        );

        $criteria = new Criteria();
        $route->prepareCriteria($criteria, new SalesChannelEntity());

        static::assertSame([], $criteria->getFilters());
        static::assertSame([], $criteria->getAssociations());
        static::assertSame([], $criteria->getSorting());
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Seo\SeoUrlRoute;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute;

/**
 * @internal
 *
 * @covers \Shopware\Storefront\Framework\Seo\SeoUrlRoute\ProductPageSeoUrlRoute
 */
class ProductPageSeoUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $productDefinition = $this->createMock(ProductDefinition::class);
        $route = new ProductPageSeoUrlRoute($productDefinition);

        $config = $route->getConfig();
        static::assertSame($productDefinition, $config->getDefinition());
        static::assertSame(ProductPageSeoUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame(ProductPageSeoUrlRoute::DEFAULT_TEMPLATE, $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
    }

    public function testCriteria(): void
    {
        $_SERVER['FEATURE_NEXT_13410'] = '1';

        $route = new ProductPageSeoUrlRoute($this->createMock(ProductDefinition::class));

        $criteria = new Criteria();
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId('test');
        $route->prepareCriteria($criteria, $salesChannel);
        static::assertTrue($criteria->hasEqualsFilter('active'));

        if (!Feature::isActive('v6.5.0.0') && !Feature::isActive('PERFORMANCE_TWEAKS')) {
            static::assertTrue($criteria->hasAssociation('mainCategories'));
            static::assertTrue($criteria->hasAssociation('categories'));
        }

        static::assertTrue($criteria->hasEqualsFilter('visibilities.salesChannelId'));

        unset($_SERVER['FEATURE_NEXT_13410']);
    }

    public function testMappingWithInvalidEntity(): void
    {
        $route = new ProductPageSeoUrlRoute($this->createMock(ProductDefinition::class));

        static::expectException(\InvalidArgumentException::class);
        $route->getMapping(new ArrayEntity(), new SalesChannelEntity());
    }

    public function testMapping(): void
    {
        $route = new ProductPageSeoUrlRoute($this->createMock(ProductDefinition::class));

        $product = new ProductEntity();
        $product->setId('test');
        $data = $route->getMapping($product, new SalesChannelEntity());

        static::assertNull($data->getError());
        static::assertSame($product, $data->getEntity());
        static::assertSame(['productId' => 'test'], $data->getInfoPathContext());

        $context = $data->getSeoPathInfoContext();
        static::assertIsArray($context);
        static::assertArrayHasKey('product', $context);
        static::assertSame($product->jsonSerialize(), $context['product']);
    }
}

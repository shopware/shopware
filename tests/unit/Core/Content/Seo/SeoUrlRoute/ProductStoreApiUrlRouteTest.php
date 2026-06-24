<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Seo\SeoUrlRoute\ProductStoreApiUrlRoute;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductStoreApiUrlRoute::class)]
class ProductStoreApiUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $definition = new ProductDefinition();
        $config = (new ProductStoreApiUrlRoute($definition))->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame(ProductStoreApiUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame('store-api.product.detail', $config->getRouteName());
        static::assertSame('', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['productId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }
}

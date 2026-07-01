<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrlRoute\CategoryStoreApiUrlRoute;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(CategoryStoreApiUrlRoute::class)]
class CategoryStoreApiUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $definition = new CategoryDefinition();
        $config = (new CategoryStoreApiUrlRoute($definition))->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame(CategoryStoreApiUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame('store-api.category.detail', $config->getRouteName());
        static::assertSame('', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['navigationId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }
}

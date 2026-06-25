<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Seo;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrlRoute\SeoUrlRouteRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Seo\SeoUrlRouteNameEnumProvider;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(SeoUrlRouteNameEnumProvider::class)]
class SeoUrlRouteNameEnumProviderTest extends TestCase
{
    public function testIsSupported(): void
    {
        $provider = new SeoUrlRouteNameEnumProvider($this->createMock(SeoUrlRouteRegistry::class));

        static::assertTrue($provider->isSupported(SeoUrlDefinition::ENTITY_NAME, 'routeName'));
        static::assertFalse($provider->isSupported('product', 'routeName'));
        static::assertFalse($provider->isSupported(SeoUrlDefinition::ENTITY_NAME, 'name'));
    }

    public function testGetEnumValues(): void
    {
        $registry = $this->createMock(SeoUrlRouteRegistry::class);
        $registry->method('getSeoUrlRoutes')->willReturn([
            'frontend.detail.page' => new \stdClass(),
            'frontend.navigation.page' => new \stdClass(),
        ]);

        $provider = new SeoUrlRouteNameEnumProvider($registry);

        static::assertSame(
            ['frontend.detail.page', 'frontend.navigation.page'],
            $provider->getChoices()
        );
    }
}

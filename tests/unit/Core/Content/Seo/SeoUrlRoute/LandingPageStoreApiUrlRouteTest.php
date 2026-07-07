<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Seo\SeoUrlRoute;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\Seo\SeoUrlRoute\LandingPageStoreApiUrlRoute;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(LandingPageStoreApiUrlRoute::class)]
class LandingPageStoreApiUrlRouteTest extends TestCase
{
    public function testGetConfig(): void
    {
        $definition = new LandingPageDefinition();
        $config = (new LandingPageStoreApiUrlRoute($definition))->getConfig();

        static::assertSame($definition, $config->getDefinition());
        static::assertSame(LandingPageStoreApiUrlRoute::ROUTE_NAME, $config->getRouteName());
        static::assertSame('store-api.landing-page.detail', $config->getRouteName());
        static::assertSame('', $config->getTemplate());
        static::assertTrue($config->getSkipInvalid());
        static::assertSame(['landingPageId' => 'abc123'], $config->getPrimaryKeyParameter('abc123'));
    }
}

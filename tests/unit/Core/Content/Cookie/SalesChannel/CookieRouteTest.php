<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Framework\Cookie\CookieProvider;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CookieRoute::class)]
class CookieRouteTest extends TestCase
{
    public function testResponseDoesNotIncludeGoogleAnalyticsCookieByDefault(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);

        $cookieRoute = new CookieRoute(
            new CookieProvider(),
            $this->createMock(SystemConfigService::class),
            $repository
        );

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);
        $cookieGroups = $response->getCookieGroups();

        $this->assertGoogleAnalyticsCookie(false, $cookieGroups);
    }

    public function testResponseIncludesGoogleAnalyticsCookieIfActive(): void
    {
        $request = new Request();
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId($analyticsId);
        $analytics->setActive(true);

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);

        $cookieRoute = new CookieRoute(
            new CookieProvider(),
            $this->createMock(SystemConfigService::class),
            $repository
        );

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);
        $cookieGroups = $response->getCookieGroups();

        $this->assertGoogleAnalyticsCookie(true, $cookieGroups);
    }

    public function testResponseDoesNotIncludesGoogleAnalyticsCookieIfNotActive(): void
    {
        $request = new Request();
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId($analyticsId);
        $analytics->setActive(false);

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);

        $cookieRoute = new CookieRoute(
            new CookieProvider(),
            $this->createMock(SystemConfigService::class),
            $repository
        );

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);
        $cookieGroups = $response->getCookieGroups();

        $this->assertGoogleAnalyticsCookie(false, $cookieGroups);
    }

    public function testWishlistCookieFilteringWhenDisabled(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')
            ->willReturnCallback(function (string $key, ?string $salesChannelId = null) {
                if ($key === 'core.cart.wishlistEnabled') {
                    return false;
                }

                return false;
            });

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);

        $cookieRoute = new CookieRoute(
            new CookieProvider(),
            $systemConfigService,
            $repository
        );

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);
        $cookieGroups = $response->getCookieGroups();

        $this->assertWishlistCookie(false, $cookieGroups);
    }

    public function testWishlistCookieFilteringWhenEnabled(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')
            ->willReturnCallback(function (string $key, ?string $salesChannelId = null) {
                if ($key === 'core.cart.wishlistEnabled') {
                    return true;
                }

                return false;
            });

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);

        $cookieRoute = new CookieRoute(
            new CookieProvider(),
            $systemConfigService,
            $repository
        );

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);
        $cookieGroups = $response->getCookieGroups();

        $this->assertWishlistCookie(true, $cookieGroups);
    }

    public function testGoogleReCaptchaCookieFilteringWhenDisabled(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')
            ->willReturn(false); // All captcha configs return false

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);

        $cookieRoute = new CookieRoute(
            new CookieProvider(),
            $systemConfigService,
            $repository
        );

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);
        $cookieGroups = $response->getCookieGroups();

        $this->assertGoogleReCaptchaCookie(false, $cookieGroups);
    }

    public function testGoogleReCaptchaCookieFilteringWhenEnabled(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')
            ->willReturnCallback(function (string $key, ?string $salesChannelId = null) {
                if ($key === 'core.basicInformation.activeCaptchasV2.googleReCaptchaV2.isActive'
                    || $key === 'core.basicInformation.activeCaptchasV2.googleReCaptchaV3.isActive') {
                    return true;
                }

                return false;
            });

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);

        $cookieRoute = new CookieRoute(
            new CookieProvider(),
            $systemConfigService,
            $repository
        );

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);
        $cookieGroups = $response->getCookieGroups();

        $this->assertGoogleReCaptchaCookie(true, $cookieGroups);
    }

    private function assertGoogleAnalyticsCookie(bool $expected, CookieGroupCollection $cookieGroups): void
    {
        $googleAnalyticsCookie = array_filter($cookieGroups->getElements(), static function (CookieGroup $cookieGroup) {
            return \count(array_filter($cookieGroup->getEntries(), static function (CookieEntry $cookie) {
                return \in_array($cookie->getCookie(), ['google-analytics-enabled', 'google-ads-enabled'], true);
            })) > 0;
        });

        if ($expected) {
            static::assertNotEmpty($googleAnalyticsCookie);
            static::assertCount(2, $googleAnalyticsCookie);
        } else {
            static::assertEmpty($googleAnalyticsCookie);
        }
    }

    private function assertWishlistCookie(bool $expected, CookieGroupCollection $cookieGroups): void
    {
        $wishlistCookieFound = false;
        foreach ($cookieGroups->getElements() as $cookieGroup) {
            foreach ($cookieGroup->getEntries() as $cookie) {
                if ($cookie->getSnippetName() === 'cookie.groupComfortFeaturesWishlist') {
                    $wishlistCookieFound = true;
                    break 2;
                }
            }
        }

        static::assertSame($expected, $wishlistCookieFound);
    }

    private function assertGoogleReCaptchaCookie(bool $expected, CookieGroupCollection $cookieGroups): void
    {
        $reCaptchaCookieFound = false;
        foreach ($cookieGroups->getElements() as $cookieGroup) {
            foreach ($cookieGroup->getEntries() as $cookie) {
                if ($cookie->getSnippetName() === 'cookie.groupRequiredCaptcha') {
                    $reCaptchaCookieFound = true;
                    break 2;
                }
            }
        }

        static::assertSame($expected, $reCaptchaCookieFound);
    }
}

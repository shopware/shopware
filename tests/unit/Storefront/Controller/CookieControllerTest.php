<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Storefront\Controller\CookieController;
use Shopware\Storefront\Framework\Cookie\CookieProvider;

/**
 * @internal
 */
#[CoversClass(CookieController::class)]
class CookieControllerTest extends TestCase
{
    public function testResponseDoesNotIncludeGoogleAnalyticsCookieByDefault(): void
    {
        $salesChannelContext = Generator::createSalesChannelContext();

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);

        $controller = new CookieControllerTestClass(
            new CookieProvider(),
            $this->createMock(SystemConfigService::class),
            $repository
        );

        $controller->offcanvas($salesChannelContext);
        $cookieGroups = $controller->renderStorefrontParameters['cookieGroups'];

        $this->assertGoogleAnalyticsCookie(false, $cookieGroups);
    }

    public function testResponseIncludesGoogleAnalyticsCookieIfActive(): void
    {
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::createSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId($analyticsId);
        $analytics->setActive(true);

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);

        $controller = new CookieControllerTestClass(
            new CookieProvider(),
            $this->createMock(SystemConfigService::class),
            $repository
        );

        $controller->offcanvas($salesChannelContext);
        $cookieGroups = $controller->renderStorefrontParameters['cookieGroups'];

        $this->assertGoogleAnalyticsCookie(true, $cookieGroups);
    }

    public function testResponseDoesNotIncludesGoogleAnalyticsCookieIfNotActive(): void
    {
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::createSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);
        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId($analyticsId);
        $analytics->setActive(false);

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);

        $controller = new CookieControllerTestClass(
            new CookieProvider(),
            $this->createMock(SystemConfigService::class),
            $repository
        );

        $controller->offcanvas($salesChannelContext);
        $cookieGroups = $controller->renderStorefrontParameters['cookieGroups'];

        $this->assertGoogleAnalyticsCookie(false, $cookieGroups);
    }

    /**
     * @param array<string, mixed> $cookieGroups
     */
    private function assertGoogleAnalyticsCookie(bool $expected, array $cookieGroups = []): void
    {
        $googleAnalyticsCookie = array_filter($cookieGroups, static function (array $cookieGroup) {
            return \count(array_filter($cookieGroup['entries'], static function (array $cookie) {
                return \in_array($cookie['cookie'], ['google-analytics-enabled', 'google-ads-enabled'], true);
            })) > 0;
        });

        if ($expected) {
            static::assertNotEmpty($googleAnalyticsCookie);
            static::assertCount(2, $googleAnalyticsCookie);
        } else {
            static::assertEmpty($googleAnalyticsCookie);
        }
    }
}

/**
 * @internal
 */
class CookieControllerTestClass extends CookieController
{
    use StorefrontControllerMockTrait;
}

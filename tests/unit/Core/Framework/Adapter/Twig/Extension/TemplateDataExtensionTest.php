<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\Doctrine\FakeConnection;
use Shopware\Storefront\Controller\NavigationController;
use Shopware\Storefront\Framework\Twig\NavigationInfo;
use Shopware\Storefront\Framework\Twig\TemplateDataExtension;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[CoversClass(TemplateDataExtension::class)]
class TemplateDataExtensionTest extends TestCase
{
    public function testGetGlobalsWithoutRequest(): void
    {
        $globals = (new TemplateDataExtension(
            new RequestStack(),
            true,
            new FakeConnection([])
        ))->getGlobals();

        static::assertSame([], $globals);
    }

    public function testGetGlobalsWithoutSalesChannelContextInRequest(): void
    {
        $globals = (new TemplateDataExtension(
            new RequestStack([new Request()]),
            true,
            new FakeConnection([])
        ))->getGlobals();

        static::assertSame([], $globals);
    }

    public function testGetGlobals(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();
        $activeRoute = 'frontend.home.page';
        $controller = NavigationController::class;
        $themeId = Uuid::randomHex();
        $expectedMinSearchLength = 3;

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext,
            '_route' => $activeRoute,
            '_controller' => $controller . '::index',
            SalesChannelRequest::ATTRIBUTE_THEME_ID => $themeId,
        ]);

        $globals = (new TemplateDataExtension(
            new RequestStack([$request]),
            true,
            new FakeConnection([['minSearchLength' => (string) $expectedMinSearchLength]])
        ))->getGlobals();

        static::assertArrayHasKey('shopware', $globals);
        static::assertArrayHasKey('dateFormat', $globals['shopware']);
        static::assertSame('Y-m-d\TH:i:sP', $globals['shopware']['dateFormat']);
        static::assertArrayHasKey('navigation', $globals['shopware']);
        static::assertInstanceOf(NavigationInfo::class, $globals['shopware']['navigation']);
        static::assertSame($salesChannelContext->getSalesChannel()->getNavigationCategoryId(), $globals['shopware']['navigation']->id);
        static::assertArrayHasKey('minSearchLength', $globals['shopware']);
        static::assertSame($expectedMinSearchLength, $globals['shopware']['minSearchLength']);
        static::assertArrayHasKey('showStagingBanner', $globals['shopware']);
        static::assertTrue($globals['shopware']['showStagingBanner']);

        static::assertArrayHasKey('themeId', $globals);
        static::assertSame($themeId, $globals['themeId']);

        static::assertArrayHasKey('controllerName', $globals);
        static::assertSame('Navigation', $globals['controllerName']);
        static::assertArrayHasKey('controllerAction', $globals);
        static::assertSame('index', $globals['controllerAction']);

        static::assertArrayHasKey('context', $globals);
        static::assertSame($salesChannelContext, $globals['context']);

        static::assertArrayHasKey('activeRoute', $globals);
        static::assertSame($activeRoute, $globals['activeRoute']);

        static::assertArrayHasKey('formViolations', $globals);
        static::assertNull($globals['formViolations']);
    }
}

<?php

declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Adapter\Twig\Extension;

use Doctrine\DBAL\Connection;
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
        $navigationId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();

        $request = new Request(attributes: [
            PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT => $salesChannelContext,
            '_route' => $activeRoute,
            '_controller' => $controller . '::index',
            SalesChannelRequest::ATTRIBUTE_THEME_ID => $themeId,
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->expects(static::exactly(2))
            ->method('fetchOne')
            ->willReturnCallback(function (string $query) use ($expectedMinSearchLength, $navigationId) {
                if ($query === 'SELECT path FROM category WHERE id = :id') {
                    return $navigationId . '|019503b99fb57238a79d33ec1461e512|019503c1e1397116a3a7c754858927ef|';
                }
                if ($query === 'SELECT `min_search_length` FROM `product_search_config` WHERE `language_id` = :id') {
                    return $expectedMinSearchLength;
                }

                throw new \RuntimeException('Unexpected query: ' . $query);
            });

        $globals = (new TemplateDataExtension(
            new RequestStack([$request]),
            true,
            $connection,
        ))->getGlobals();

        static::assertArrayHasKey('shopware', $globals);
        static::assertArrayHasKey('dateFormat', $globals['shopware']);
        static::assertSame('Y-m-d\TH:i:sP', $globals['shopware']['dateFormat']);
        static::assertArrayHasKey('navigation', $globals['shopware']);
        $navigationInfo = $globals['shopware']['navigation'];
        static::assertInstanceOf(NavigationInfo::class, $navigationInfo);
        static::assertSame($salesChannelContext->getSalesChannel()->getNavigationCategoryId(), $navigationInfo->id);
        // Make sure, the root category is not part of the pathIdList
        static::assertSame(['019503b99fb57238a79d33ec1461e512', '019503c1e1397116a3a7c754858927ef'], $navigationInfo->pathIdList);
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

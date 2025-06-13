<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CookieRoute::class)]
class CookieRouteTest extends TestCase
{
    public function testGetCookieGroupsWithEmptyProvider(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->method('getCookieGroups')->willReturn([]);

        $cookieService = $this->createMock(CookieService::class);
        $cookieService->expects($this->never())->method('getCookieGroupCollection');

        $cookieRoute = new CookieRoute($cookieProvider, $cookieService);

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);
        $cookieGroups = $response->getCookieGroups();

        static::assertCount(0, $cookieGroups);
    }

    public function testGetCookieGroupsCallsServiceMethods(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $mockCookieGroups = [
            [
                'snippet_name' => 'test.group',
                'entries' => [
                    [
                        'snippet_name' => 'test.cookie',
                        'cookie' => 'test-cookie',
                    ],
                ],
            ],
        ];

        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->method('getCookieGroups')->willReturn($mockCookieGroups);

        $cookieService = $this->createMock(CookieService::class);
        $expectedCollection = new CookieGroupCollection();

        $cookieService->expects($this->once())
            ->method('getCookieGroupCollection')
            ->with($mockCookieGroups, $salesChannelContext, true)
            ->willReturn($expectedCollection);

        $cookieRoute = new CookieRoute($cookieProvider, $cookieService);

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);
        $cookieGroups = $response->getCookieGroups();

        static::assertSame($expectedCollection, $cookieGroups);
    }

    public function testGetCookieGroupsReturnsCorrectResponseType(): void
    {
        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->method('getCookieGroups')->willReturn([
            ['snippet_name' => 'test.group'],
        ]);

        $expectedCollection = new CookieGroupCollection();
        $cookieService = $this->createMock(CookieService::class);
        $cookieService->method('getCookieGroupCollection')
            ->willReturn($expectedCollection);

        $cookieRoute = new CookieRoute($cookieProvider, $cookieService);

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);

        static::assertSame($expectedCollection, $response->getCookieGroups());
    }

    public function testGetCookieGroupsWithTranslateParameterTrue(): void
    {
        $request = new Request();
        $request->query->set('translate', true);
        $salesChannelContext = Generator::generateSalesChannelContext();

        $mockCookieGroups = [
            [
                'snippet_name' => 'test.group',
                'entries' => [
                    [
                        'snippet_name' => 'test.cookie',
                        'cookie' => 'test-cookie',
                    ],
                ],
            ],
        ];

        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->method('getCookieGroups')->willReturn($mockCookieGroups);

        $cookieService = $this->createMock(CookieService::class);
        $expectedCollection = new CookieGroupCollection();

        $cookieService->expects($this->once())
            ->method('getCookieGroupCollection')
            ->with($mockCookieGroups, $salesChannelContext, true)
            ->willReturn($expectedCollection);

        $cookieRoute = new CookieRoute($cookieProvider, $cookieService);

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);

        static::assertSame($expectedCollection, $response->getCookieGroups());
    }

    public function testGetCookieGroupsWithTranslateParameterFalse(): void
    {
        $request = new Request();
        $request->query->set('translate', false);
        $salesChannelContext = Generator::generateSalesChannelContext();

        $mockCookieGroups = [
            [
                'snippet_name' => 'test.group',
                'entries' => [
                    [
                        'snippet_name' => 'test.cookie',
                        'cookie' => 'test-cookie',
                    ],
                ],
            ],
        ];

        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->method('getCookieGroups')->willReturn($mockCookieGroups);

        $cookieService = $this->createMock(CookieService::class);
        $expectedCollection = new CookieGroupCollection();

        $cookieService->expects($this->once())
            ->method('getCookieGroupCollection')
            ->with($mockCookieGroups, $salesChannelContext, false)
            ->willReturn($expectedCollection);

        $cookieRoute = new CookieRoute($cookieProvider, $cookieService);

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);

        static::assertSame($expectedCollection, $response->getCookieGroups());
    }

    public function testGetCookieGroupsDefaultsToTranslateTrue(): void
    {
        $request = new Request(); // No translate parameter
        $salesChannelContext = Generator::generateSalesChannelContext();

        $mockCookieGroups = [
            [
                'snippet_name' => 'test.group',
                'entries' => [],
            ],
        ];

        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->method('getCookieGroups')->willReturn($mockCookieGroups);

        $cookieService = $this->createMock(CookieService::class);
        $expectedCollection = new CookieGroupCollection();

        $cookieService->expects($this->once())
            ->method('getCookieGroupCollection')
            ->with($mockCookieGroups, $salesChannelContext, true)
            ->willReturn($expectedCollection);

        $cookieRoute = new CookieRoute($cookieProvider, $cookieService);

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);

        static::assertSame($expectedCollection, $response->getCookieGroups());
    }
}

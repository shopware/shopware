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
        // Service methods should not be called when provider returns empty array
        $cookieService->expects($this->never())->method('filterGoogleAnalyticsCookie');
        $cookieService->expects($this->never())->method('filterWishlistCookie');
        $cookieService->expects($this->never())->method('filterGoogleReCaptchaCookie');
        $cookieService->expects($this->never())->method('convertToCookieGroupCollection');

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

        // Verify all service methods are called in the correct order with correct parameters
        $cookieService->expects($this->once())
            ->method('filterGoogleAnalyticsCookie')
            ->with($salesChannelContext, $mockCookieGroups)
            ->willReturn($mockCookieGroups);

        $cookieService->expects($this->once())
            ->method('filterWishlistCookie')
            ->with($salesChannelContext->getSalesChannelId(), $mockCookieGroups)
            ->willReturn($mockCookieGroups);

        $cookieService->expects($this->once())
            ->method('filterGoogleReCaptchaCookie')
            ->with($salesChannelContext->getSalesChannelId(), $mockCookieGroups)
            ->willReturn($mockCookieGroups);

        $cookieService->expects($this->once())
            ->method('translateCookieGroups')
            ->with($mockCookieGroups, $salesChannelContext)
            ->willReturn($mockCookieGroups);

        $expectedCollection = new CookieGroupCollection();
        $cookieService->expects($this->once())
            ->method('convertToCookieGroupCollection')
            ->with($mockCookieGroups)
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
        $cookieService->method('filterGoogleAnalyticsCookie')->willReturnArgument(1);
        $cookieService->method('filterWishlistCookie')->willReturnArgument(1);
        $cookieService->method('filterGoogleReCaptchaCookie')->willReturnArgument(1);
        $cookieService->method('translateCookieGroups')->willReturnArgument(0);
        $cookieService->method('convertToCookieGroupCollection')->willReturn($expectedCollection);

        $cookieRoute = new CookieRoute($cookieProvider, $cookieService);

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);

        // Verify the response contains the expected collection from the service
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

        $translatedCookieGroups = [
            [
                'snippet_name' => 'Translated Group',
                'entries' => [
                    [
                        'snippet_name' => 'Translated Cookie',
                        'cookie' => 'test-cookie',
                    ],
                ],
            ],
        ];

        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->method('getCookieGroups')->willReturn($mockCookieGroups);

        $cookieService = $this->createMock(CookieService::class);
        $cookieService->method('filterGoogleAnalyticsCookie')->willReturn($mockCookieGroups);
        $cookieService->method('filterWishlistCookie')->willReturn($mockCookieGroups);
        $cookieService->method('filterGoogleReCaptchaCookie')->willReturn($mockCookieGroups);

        // Expect translation to be called
        $cookieService->expects($this->once())
            ->method('translateCookieGroups')
            ->with($mockCookieGroups, $salesChannelContext)
            ->willReturn($translatedCookieGroups);

        $expectedCollection = new CookieGroupCollection();
        $cookieService->method('convertToCookieGroupCollection')
            ->with($translatedCookieGroups)
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
        $cookieService->method('filterGoogleAnalyticsCookie')->willReturn($mockCookieGroups);
        $cookieService->method('filterWishlistCookie')->willReturn($mockCookieGroups);
        $cookieService->method('filterGoogleReCaptchaCookie')->willReturn($mockCookieGroups);

        // Expect translation NOT to be called
        $cookieService->expects($this->never())->method('translateCookieGroups');

        $expectedCollection = new CookieGroupCollection();
        $cookieService->method('convertToCookieGroupCollection')
            ->with($mockCookieGroups)
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
        $cookieService->method('filterGoogleAnalyticsCookie')->willReturn($mockCookieGroups);
        $cookieService->method('filterWishlistCookie')->willReturn($mockCookieGroups);
        $cookieService->method('filterGoogleReCaptchaCookie')->willReturn($mockCookieGroups);

        // Expect translation to be called by default
        $cookieService->expects($this->once())
            ->method('translateCookieGroups')
            ->with($mockCookieGroups, $salesChannelContext)
            ->willReturn($mockCookieGroups);

        $expectedCollection = new CookieGroupCollection();
        $cookieService->method('convertToCookieGroupCollection')->willReturn($expectedCollection);

        $cookieRoute = new CookieRoute($cookieProvider, $cookieService);

        $response = $cookieRoute->getCookieGroups($request, $salesChannelContext);

        static::assertSame($expectedCollection, $response->getCookieGroups());
    }
}

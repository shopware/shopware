<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\SalesChannel\CookieHashRoute;
use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CookieHashRoute::class)]
class CookieHashRouteTest extends TestCase
{
    public function testGetCookieHashWithEmptyProvider(): void
    {
        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->method('getCookieGroups')->willReturn([]);

        $cookieService = $this->createMock(CookieService::class);
        $cookieService->method('calculateCookieHash')->willReturn('8739602554c7f3241958e3cc9b57fdecb474d508');

        $route = new CookieHashRoute($cookieProvider, $cookieService);

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $response = $route->getCookieHash($request, $salesChannelContext);

        static::assertIsString($response->getCookieHash());
    }

    public function testGetCookieHashCallsServiceMethods(): void
    {
        $cookieGroups = [
            ['group' => 'test', 'cookie' => 'test-cookie'],
        ];

        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->method('getCookieGroups')->willReturn($cookieGroups);

        $collection = new CookieGroupCollection();

        $cookieService = $this->createMock(CookieService::class);
        $cookieService->expects($this->once())
            ->method('getCookieGroupCollection')
            ->with($cookieGroups, static::anything(), true)
            ->willReturn($collection);
        $cookieService->expects($this->once())
            ->method('calculateCookieHash')
            ->with($collection)
            ->willReturn('f1d2d2f924e986ac86fdf7b36c94bcdf32beec15');

        $route = new CookieHashRoute($cookieProvider, $cookieService);

        $request = new Request();
        $salesChannelContext = Generator::generateSalesChannelContext();

        $response = $route->getCookieHash($request, $salesChannelContext);

        // Response is properly typed, no need to assert instance type
    }

    /**
     * @param array<string, string> $queryParams
     */
    #[DataProvider('translateParameterProvider')]
    public function testTranslateParameterHandling(array $queryParams, bool $expectedTranslate): void
    {
        $cookieGroups = [
            ['group' => 'test', 'cookie' => 'test-cookie'],
        ];

        $cookieProvider = $this->createMock(CookieProviderInterface::class);
        $cookieProvider->method('getCookieGroups')->willReturn($cookieGroups);

        $collection = new CookieGroupCollection();

        $cookieService = $this->createMock(CookieService::class);
        $cookieService->expects($this->once())
            ->method('getCookieGroupCollection')
            ->with($cookieGroups, static::anything(), $expectedTranslate)
            ->willReturn($collection);
        $cookieService->method('calculateCookieHash')->willReturn('hash');

        $route = new CookieHashRoute($cookieProvider, $cookieService);

        $request = new Request($queryParams);
        $salesChannelContext = Generator::generateSalesChannelContext();

        $route->getCookieHash($request, $salesChannelContext);
    }

    /**
     * @return array<string, array{array<string, string>, bool}>
     */
    public static function translateParameterProvider(): array
    {
        return [
            'translate=true' => [['translate' => '1'], true],
            'translate=false' => [['translate' => '0'], false],
            'no translate param (defaults to true)' => [[], true],
        ];
    }
}

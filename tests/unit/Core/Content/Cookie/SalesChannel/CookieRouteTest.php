<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CookieRoute::class)]
class CookieRouteTest extends TestCase
{
    public function testGetCookieGroupsCallsRemoveAndTranslateMethod(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->setEntries(new CookieEntryCollection([new CookieEntry('test-cookie')]));
        $expectedCookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieProvider = $this->createMock(CookieProvider::class);
        $cookieProvider->method('getCookieGroups')->willReturn($expectedCookieGroups);

        $cookieService = $this->createMock(CookieService::class);

        $cookieService->expects($this->once())
            ->method('removeCookieGroupsWithoutCookies')
            ->with($expectedCookieGroups);
        $cookieService->expects($this->once())->method('translateCookieGroups');

        $response = (new CookieRoute($cookieProvider, $cookieService))
            ->getCookieGroups(new Request(), $salesChannelContext);

        static::assertSame($expectedCookieGroups, $response->getCookieGroups());
    }

    public function testGetCookieGroupsCallsTranslateMethod(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->setEntries(new CookieEntryCollection([new CookieEntry('test-cookie')]));
        $expectedCookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieProvider = $this->createMock(CookieProvider::class);
        $cookieProvider->method('getCookieGroups')->willReturn($expectedCookieGroups);

        $cookieService = $this->createMock(CookieService::class);

        $cookieService->expects($this->once())->method('removeCookieGroupsWithoutCookies');
        $cookieService->expects($this->once())->method('translateCookieGroups');

        $response = (new CookieRoute($cookieProvider, $cookieService))
            ->getCookieGroups(new Request(['translate' => true]), $salesChannelContext);

        static::assertSame($expectedCookieGroups, $response->getCookieGroups());
    }

    public function testGetCookieGroupsDoesNotCallTranslateMethod(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->setEntries(new CookieEntryCollection([new CookieEntry('test-cookie')]));
        $expectedCookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieProvider = $this->createMock(CookieProvider::class);
        $cookieProvider->method('getCookieGroups')->willReturn($expectedCookieGroups);

        $cookieService = $this->createMock(CookieService::class);

        $cookieService->expects($this->once())->method('removeCookieGroupsWithoutCookies');
        $cookieService->expects($this->never())->method('translateCookieGroups');

        $response = (new CookieRoute($cookieProvider, $cookieService))
            ->getCookieGroups(new Request(['translate' => false]), $salesChannelContext);

        static::assertSame($expectedCookieGroups, $response->getCookieGroups());
    }
}

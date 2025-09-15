<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\SalesChannel\CookieRoute;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CookieRoute::class)]
class CookieRouteTest extends TestCase
{
    public function testItThrowsDecorationPatternException(): void
    {
        $this->expectExceptionObject(new DecorationPatternException(CookieRoute::class));

        $cookieProvider = $this->createMock(CookieProvider::class);
        (new CookieRoute($cookieProvider))->getDecorated();
    }

    public function testGetCookieGroups(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroup = new CookieGroup('test.group');
        $cookieGroup->setEntries(new CookieEntryCollection([new CookieEntry('test-cookie')]));
        $expectedCookieGroups = new CookieGroupCollection([$cookieGroup]);

        $cookieProvider = $this->createMock(CookieProvider::class);
        $cookieProvider->expects($this->once())
            ->method('getCookieGroups')
            ->with($salesChannelContext)
            ->willReturn($expectedCookieGroups);

        $response = (new CookieRoute($cookieProvider))->getCookieGroups(new Request(), $salesChannelContext);

        static::assertSame($expectedCookieGroups, $response->getCookieGroups());
    }
}

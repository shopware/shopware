<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\CountryStateController;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPagelet;
use Shopware\Storefront\Pagelet\Country\CountryStateDataPageletLoader;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('fundamentals@discovery')]
#[CoversClass(CountryStateController::class)]
class CountryStateControllerTest extends TestCase
{
    private CountryStateDataPageletLoader&MockObject $pageletLoader;

    private CountryStateControllerTestClass $controller;

    protected function setUp(): void
    {
        $this->pageletLoader = static::createMock(CountryStateDataPageletLoader::class);
        $this->controller = new CountryStateControllerTestClass($this->pageletLoader);
    }

    public function testGetCountryDataUsesCountryIdFromQuery(): void
    {
        $request = new Request(['countryId' => 'query-country-id']);
        $context = Generator::generateSalesChannelContext();

        $this->pageletLoader->expects($this->once())
            ->method('load')
            ->with('query-country-id', $request, $context)
            ->willReturn(new CountryStateDataPagelet());

        $this->controller->getCountryData($request, $context);
    }

    public function testGetCountryDataFallsBackToCountryIdFromPost(): void
    {
        $request = new Request([], ['countryId' => 'post-country-id']);
        $context = Generator::generateSalesChannelContext();

        $this->pageletLoader->expects($this->once())
            ->method('load')
            ->with('post-country-id', $request, $context)
            ->willReturn(new CountryStateDataPagelet());

        $this->controller->getCountryData($request, $context);
    }

    public function testGetCountryDataThrowsExceptionWithoutCountryId(): void
    {
        $this->pageletLoader->expects($this->never())
            ->method('load');

        $this->expectExceptionObject(RoutingException::missingRequestParameter('countryId'));

        $this->controller->getCountryData(new Request(), Generator::generateSalesChannelContext());
    }
}

/**
 * @internal
 */
class CountryStateControllerTestClass extends CountryStateController
{
    use StorefrontControllerMockTrait;
}

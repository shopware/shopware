<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Test\Annotation\DisabledFeatures;
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

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetCountryDataFallsBackToCountryIdFromPost(): void
    {
        $request = Request::create('/country/country-state-data', Request::METHOD_POST, ['countryId' => 'post-country-id']);
        $context = Generator::generateSalesChannelContext();

        $this->pageletLoader->expects($this->once())
            ->method('load')
            ->with('post-country-id', $request, $context)
            ->willReturn(new CountryStateDataPagelet());

        $this->controller->getCountryData($request, $context);
    }

    /**
     * @deprecated tag:v6.8.0 - Remove when the v6.8.0.0 feature flag is removed
     */
    public function testGetCountryDataThrowsForPostRequestsWhenV6800IsActive(): void
    {
        $this->pageletLoader->expects($this->never())
            ->method('load');

        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: The POST request to /country/country-state-data is deprecated and will be removed in v6.8.0.0. Use a GET request instead.'
        ));

        $this->controller->getCountryData(
            Request::create('/country/country-state-data', Request::METHOD_POST, ['countryId' => 'post-country-id']),
            Generator::generateSalesChannelContext()
        );
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

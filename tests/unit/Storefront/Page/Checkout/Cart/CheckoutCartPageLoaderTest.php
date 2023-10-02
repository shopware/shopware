<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Checkout\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerAddress\CustomerAddressEntity;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Core\System\Country\SalesChannel\CountryRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPage;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Page\MetaInformation;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CheckoutCartPageLoader::class)]
class CheckoutCartPageLoaderTest extends TestCase
{
    public function testRobotsMetaSetIfGiven(): void
    {
        $page = new CheckoutCartPage();
        $page->setMetaInformation(new MetaInformation());

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $checkoutCartPageLoader = new CheckoutCartPageLoader(
            $pageLoader,
            $this->createMock(EventDispatcher::class),
            $this->createMock(StorefrontCartFacade::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class),
            $this->createMock(CountryRoute::class)
        );

        $page = $checkoutCartPageLoader->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertNotNull($page->getMetaInformation());
        static::assertSame('noindex,follow', $page->getMetaInformation()->getRobots());
    }

    public function testRobotsMetaNotSetIfGiven(): void
    {
        $page = new CheckoutCartPage();

        $pageLoader = $this->createMock(GenericPageLoader::class);
        $pageLoader
            ->method('load')
            ->willReturn($page);

        $checkoutCartPageLoader = new CheckoutCartPageLoader(
            $pageLoader,
            $this->createMock(EventDispatcher::class),
            $this->createMock(StorefrontCartFacade::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class),
            $this->createMock(CountryRoute::class)
        );

        $page = $checkoutCartPageLoader->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        static::assertNull($page->getMetaInformation());
    }

    public function testPaymentShippingAndCountryMethodsAreSetToPage(): void
    {
        $paymentMethods = new PaymentMethodCollection([
            (new PaymentMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new PaymentMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $shippingMethods = new ShippingMethodCollection([
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
            (new ShippingMethodEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $countries = new CountryCollection([
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 0]),
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 1]),
        ]);

        $response = new CheckoutGatewayRouteResponse(
            $paymentMethods,
            $shippingMethods,
            new ErrorCollection()
        );

        $countryResponse = new CountryRouteResponse(
            new EntitySearchResult(
                CountryDefinition::ENTITY_NAME,
                2,
                $countries,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $checkoutGatewayRoute = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $checkoutGatewayRoute
            ->method('load')
            ->withAnyParameters()
            ->willReturn($response);

        $countryRoute = $this->createMock(CountryRoute::class);
        $countryRoute
            ->method('load')
            ->withAnyParameters()
            ->willReturn($countryResponse);

        $checkoutCartPageLoader = new CheckoutCartPageLoader(
            $this->createMock(GenericPageLoader::class),
            $this->createMock(EventDispatcher::class),
            $this->createMock(StorefrontCartFacade::class),
            $checkoutGatewayRoute,
            $countryRoute
        );

        $page = $checkoutCartPageLoader->load(
            new Request(),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame($paymentMethods, $page->getPaymentMethods());
        static::assertSame($shippingMethods, $page->getShippingMethods());
        static::assertSame($countries, $page->getCountries());
    }

    public function testNoCountrySetIfLoggedIn(): void
    {
        $countries = new CountryCollection([
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 0]),
            (new CountryEntity())->assign(['_uniqueIdentifier' => Uuid::randomHex(), 'position' => 1]),
        ]);

        $countryResponse = new CountryRouteResponse(
            new EntitySearchResult(
                CountryDefinition::ENTITY_NAME,
                2,
                $countries,
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $countryRoute = $this->createMock(CountryRoute::class);
        $countryRoute
            ->method('load')
            ->withAnyParameters()
            ->willReturn($countryResponse);

        $checkoutCartPageLoader = new CheckoutCartPageLoader(
            $this->createMock(GenericPageLoader::class),
            $this->createMock(EventDispatcher::class),
            $this->createMock(StorefrontCartFacade::class),
            $this->createMock(AbstractCheckoutGatewayRoute::class),
            $countryRoute
        );

        $page = $checkoutCartPageLoader->load(
            new Request(),
            $this->getContextWithDummyCustomer()
        );

        if (Feature::isActive('v6.7.0.0')) {
            static::assertEmpty($page->getCountries());
        } else {
            static::assertSame($countries, $page->getCountries());
        }
    }

    private function getContextWithDummyCustomer(?string $countryId = null): SalesChannelContext
    {
        $address = (new CustomerAddressEntity())->assign(['id' => Uuid::randomHex(), 'countryId' => $countryId ?? Uuid::randomHex()]);

        $customer = new CustomerEntity();
        $customer->assign([
            'activeBillingAddress' => $address,
            'activeShippingAddress' => $address,
        ]);

        $context = $this->createMock(SalesChannelContext::class);
        $context
            ->method('getCustomer')
            ->willReturn($customer);

        return $context;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Gateway\SalesChannel\CheckoutGatewayRouteResponse;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Tests\Integration\Storefront\Page\StorefrontPageTestBehaviour;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
class CartPageTest extends TestCase
{
    use IntegrationTestBehaviour;
    use StorefrontPageTestBehaviour;

    public function testItLoadsTheCart(): void
    {
        $request = new Request();
        $context = $this->createSalesChannelContextWithNavigation();

        $event = null;
        $this->catchEvent(CheckoutCartPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertSame(0.0, $page->getCart()->getPrice()->getNetPrice());
        static::assertSame($context->getToken(), $page->getCart()->getToken());
        self::assertPageEvent(CheckoutCartPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testAddsCurrentSelectedShippingMethod(): void
    {
        $response = new CheckoutGatewayRouteResponse(
            new PaymentMethodCollection(),
            new ShippingMethodCollection(),
            new ErrorCollection()
        );

        $route = $this->createMock(AbstractCheckoutGatewayRoute::class);
        $route
            ->method('load')
            ->willReturn($response);

        $loader = new CheckoutCartPageLoader(
            $this->getContainer()->get(GenericPageLoader::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(StorefrontCartFacade::class),
            $route,
            $this->getContainer()->get(CountryRoute::class)
        );

        $context = $this->createSalesChannelContextWithNavigation();

        $result = $loader->load(new Request(), $context);

        static::assertTrue($result->getShippingMethods()->has($context->getShippingMethod()->getId()));
    }

    protected function getPageLoader(): CheckoutCartPageLoader
    {
        return $this->getContainer()->get(CheckoutCartPageLoader::class);
    }
}

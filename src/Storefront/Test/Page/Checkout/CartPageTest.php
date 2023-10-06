<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Page\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\ShippingMethodRouteResponse;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\Country\SalesChannel\CountryRoute;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPage;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoader;
use Shopware\Storefront\Page\GenericPageLoader;
use Shopware\Storefront\Test\Page\StorefrontPageTestBehaviour;
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

        /** @var CheckoutCartPageLoadedEvent $event */
        $event = null;
        $this->catchEvent(CheckoutCartPageLoadedEvent::class, $event);

        $page = $this->getPageLoader()->load($request, $context);

        static::assertInstanceOf(CheckoutCartPage::class, $page);
        static::assertSame(0.0, $page->getCart()->getPrice()->getNetPrice());
        static::assertSame($context->getToken(), $page->getCart()->getToken());
        self::assertPageEvent(CheckoutCartPageLoadedEvent::class, $event, $context, $request, $page);
    }

    public function testAddsCurrentSelectedShippingMethod(): void
    {
        $response = new ShippingMethodRouteResponse(
            new EntitySearchResult(
                'shipping_method',
                0,
                new ShippingMethodCollection(),
                null,
                new Criteria(),
                Context::createDefaultContext()
            )
        );

        $route = $this->createMock(ShippingMethodRoute::class);
        $route->method('load')
            ->willReturn($response);

        $loader = new CheckoutCartPageLoader(
            $this->getContainer()->get(GenericPageLoader::class),
            $this->getContainer()->get('event_dispatcher'),
            $this->getContainer()->get(StorefrontCartFacade::class),
            $this->getContainer()->get(PaymentMethodRoute::class),
            $route,
            $this->getContainer()->get(CountryRoute::class)
        );

        $context = $this->createSalesChannelContextWithNavigation();

        $result = $loader->load(new Request(), $context);

        static::assertTrue($result->getShippingMethods()->has($context->getShippingMethod()->getId()));
    }

    /**
     * @return CheckoutCartPageLoader
     */
    protected function getPageLoader()
    {
        return $this->getContainer()->get(CheckoutCartPageLoader::class);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Cart;

use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\Adapter\Translation\AbstractTranslator;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Checkout\Cart\SalesChannel\StorefrontCartFacade;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Do not use direct or indirect repository calls in a PageLoader. Always use a store-api route to get or put data.
 */
#[Package('framework')]
class CheckoutCartPageLoader
{
    /**
     * @internal
     */
    public function __construct(
        private readonly GenericPageLoaderInterface $genericLoader,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly StorefrontCartFacade $cartService,
        private readonly AbstractCheckoutGatewayRoute $checkoutGatewayRoute,
        private readonly AbstractCountryRoute $countryRoute,
        private readonly AbstractTranslator $translator
    ) {
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws RoutingException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): CheckoutCartPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = CheckoutCartPage::createFrom($page);
        $this->setMetaInformation($page);

        $page->setCountries($this->getCountries($salesChannelContext));

        $cart = $this->cartService->get($salesChannelContext->getToken(), $salesChannelContext);

        $gatewayResponse = $this->checkoutGatewayRoute->load($request, $cart, $salesChannelContext);

        $page->setPaymentMethods($gatewayResponse->getPaymentMethods());
        $page->setCart($cart);

        $shippingMethods = $gatewayResponse->getShippingMethods();

        if (!$shippingMethods->has($salesChannelContext->getShippingMethod()->getId())) {
            $shippingMethods->add($salesChannelContext->getShippingMethod());
        }

        $page->setShippingMethods($shippingMethods);

        $this->eventDispatcher->dispatch(
            new CheckoutCartPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    protected function setMetaInformation(CheckoutCartPage $page): void
    {
        $page->getMetaInformation()?->setRobots('noindex,follow');
        $page->getMetaInformation()?->setMetaTitle(
            $this->translator->trans('checkout.cartMetaTitle') . ' | ' . $page->getMetaInformation()->getMetaTitle()
        );
    }

    private function getCountries(SalesChannelContext $context): CountryCollection
    {
        if ($context->getCustomer()) {
            return new CountryCollection();
        }

        $countries = $this->countryRoute->load(new Request(), new Criteria(), $context)->getCountries();
        $countries->sortByPositionAndName();

        return $countries;
    }
}

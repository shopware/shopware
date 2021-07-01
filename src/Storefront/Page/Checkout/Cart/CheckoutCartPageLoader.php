<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Cart;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Country\SalesChannel\AbstractCountryRoute;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutCartPageLoader
{
    /**
     * @var GenericPageLoaderInterface
     */
    private $genericLoader;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var AbstractPaymentMethodRoute
     */
    private $paymentMethodRoute;

    /**
     * @var AbstractShippingMethodRoute
     */
    private $shippingMethodRoute;

    /**
     * @var AbstractCountryRoute
     */
    private $countryRoute;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        AbstractPaymentMethodRoute $paymentMethodRoute,
        AbstractShippingMethodRoute $shippingMethodRoute,
        AbstractCountryRoute $countryRoute
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
        $this->paymentMethodRoute = $paymentMethodRoute;
        $this->shippingMethodRoute = $shippingMethodRoute;
        $this->countryRoute = $countryRoute;
    }

    /**
     * @throws CategoryNotFoundException
     * @throws InconsistentCriteriaIdsException
     * @throws MissingRequestParameterException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): CheckoutCartPage
    {
        $page = $this->genericLoader->load($request, $salesChannelContext);

        $page = CheckoutCartPage::createFrom($page);

        if ($page->getMetaInformation()) {
            $page->getMetaInformation()->setRobots('noindex,follow');
        }

        $page->setCountries($this->getCountries($salesChannelContext));

        $page->setPaymentMethods($this->getPaymentMethods($salesChannelContext));

        $page->setShippingMethods($this->getShippingMethods($salesChannelContext));

        $page->setCart($this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext));

        $this->eventDispatcher->dispatch(
            new CheckoutCartPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getPaymentMethods(SalesChannelContext $context): PaymentMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', '1');

        return $this->paymentMethodRoute->load($request, $context, new Criteria())->getPaymentMethods();
    }

    private function getShippingMethods(SalesChannelContext $context): ShippingMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', '1');

        $shippingMethods = $this->shippingMethodRoute
            ->load($request, $context, new Criteria())
            ->getShippingMethods();

        if (!$shippingMethods->has($context->getShippingMethod()->getId())) {
            $shippingMethods->add($context->getShippingMethod());
        }

        return $shippingMethods;
    }

    private function getCountries(SalesChannelContext $context): CountryCollection
    {
        $countries = $this->countryRoute->load(new Request(), new Criteria(), $context)->getCountries();
        $countries->sortByPositionAndName();

        return $countries;
    }
}

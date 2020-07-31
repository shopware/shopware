<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Confirm;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Checkout\Shipping\SalesChannel\AbstractShippingMethodRoute;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoaderInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutConfirmPageLoader
{
    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartService
     */
    private $cartService;

    /**
     * @var AbstractShippingMethodRoute
     */
    private $shippingMethodRoute;

    /**
     * @var AbstractPaymentMethodRoute
     */
    private $paymentMethodRoute;

    /**
     * @var GenericPageLoaderInterface
     */
    private $genericPageLoader;

    public function __construct(
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        AbstractShippingMethodRoute $shippingMethodRoute,
        AbstractPaymentMethodRoute $paymentMethodRoute,
        GenericPageLoaderInterface $genericPageLoader
    ) {
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
        $this->shippingMethodRoute = $shippingMethodRoute;
        $this->paymentMethodRoute = $paymentMethodRoute;
        $this->genericPageLoader = $genericPageLoader;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): CheckoutConfirmPage
    {
        $page = $this->genericPageLoader->load($request, $salesChannelContext);
        $page = CheckoutConfirmPage::createFrom($page);

        $page->setPaymentMethods($this->getPaymentMethods($salesChannelContext));
        $page->setShippingMethods($this->getShippingMethods($salesChannelContext));
        $page->setCart($this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext));

        $this->eventDispatcher->dispatch(
            new CheckoutConfirmPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    private function getPaymentMethods(SalesChannelContext $context): PaymentMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', true);

        return $this->paymentMethodRoute->load($request, $context)->getPaymentMethods();
    }

    private function getShippingMethods(SalesChannelContext $context): ShippingMethodCollection
    {
        $request = new Request();
        $request->query->set('onlyAvailable', true);

        return $this->shippingMethodRoute
            ->load($request, $context, new Criteria())
            ->getShippingMethods();
    }
}

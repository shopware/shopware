<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Cart;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Page\GenericPageLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutCartPageLoader
{
    /**
     * @var GenericPageLoader
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
     * @var EntityRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $shippingMethodRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $countryRepository;

    public function __construct(
        GenericPageLoader $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        EntityRepositoryInterface $paymentMethodRepository,
        EntityRepositoryInterface $shippingMethodRepository,
        EntityRepositoryInterface $countryRepository
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->countryRepository = $countryRepository;
    }

    public function load(Request $request, SalesChannelContext $context)
    {
        $page = $this->genericLoader->load($request, $context);

        $page = CheckoutCartPage::createFrom($page);

        $page->setCountries($this->getCountries($context));

        $page->setPaymentMethods($this->getPaymentMethods($context));

        $page->setShippingMethods($this->getShippingMethods($context));

        $page->setCart($this->cartService->getCart($context->getToken(), $context));

        $this->eventDispatcher->dispatch(
            CheckoutCartPageLoadedEvent::NAME,
            new CheckoutCartPageLoadedEvent($page, $context, $request)
        );

        return $page;
    }

    private function getPaymentMethods(SalesChannelContext $context): PaymentMethodCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('payment_method.salesChannels.id', $context->getSalesChannel()->getId()));

        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $this->paymentMethodRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $paymentMethods->filterByActiveRules($context);
    }

    private function getShippingMethods(SalesChannelContext $context): ShippingMethodCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('shipping_method.salesChannels.id', $context->getSalesChannel()->getId()));

        /** @var ShippingMethodCollection $shippingMethods */
        $shippingMethods = $this->shippingMethodRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $shippingMethods->filterByActiveRules($context);
    }

    private function getCountries(SalesChannelContext $context): CountryCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addFilter(new EqualsFilter('country.salesChannels.id', $context->getSalesChannel()->getId()))
        ;

        /** @var CountryCollection $countries */
        $countries = $this->countryRepository
            ->search($criteria, $context->getContext())
            ->getEntities();

        return $countries;
    }
}

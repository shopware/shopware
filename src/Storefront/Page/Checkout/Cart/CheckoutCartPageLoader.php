<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Cart;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
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
     * @var SalesChannelRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $shippingMethodRepository;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $countryRepository;

    public function __construct(
        GenericPageLoaderInterface $genericLoader,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService,
        SalesChannelRepositoryInterface $paymentMethodRepository,
        SalesChannelRepositoryInterface $shippingMethodRepository,
        SalesChannelRepositoryInterface $countryRepository
    ) {
        $this->genericLoader = $genericLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->countryRepository = $countryRepository;
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

        $page->setCountries($this->getCountries($salesChannelContext));

        $page->setPaymentMethods($this->getPaymentMethods($salesChannelContext));

        $page->setShippingMethods($this->getShippingMethods($salesChannelContext));

        $page->setCart($this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext));

        $this->eventDispatcher->dispatch(
            new CheckoutCartPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getPaymentMethods(SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true));

        /** @var PaymentMethodCollection $paymentMethods */
        $paymentMethods = $this->paymentMethodRepository->search($criteria, $salesChannelContext)->getEntities();

        $paymentMethods->sort(function (PaymentMethodEntity $a, PaymentMethodEntity $b) {
            return $a->getPosition() <=> $b->getPosition();
        });

        return $paymentMethods->filterByActiveRules($salesChannelContext);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getShippingMethods(SalesChannelContext $salesChannelContext): ShippingMethodCollection
    {
        $criteria = (new Criteria())->addFilter(new EqualsFilter('active', true));

        /** @var ShippingMethodCollection $shippingMethods */
        $shippingMethods = $this->shippingMethodRepository->search($criteria, $salesChannelContext)->getEntities();

        return $shippingMethods->filterByActiveRules($salesChannelContext);
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getCountries(SalesChannelContext $salesChannelContext): CountryCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true));

        /** @var CountryCollection $countries */
        $countries = $this->countryRepository->search($criteria, $salesChannelContext)->getEntities();

        return $countries;
    }
}

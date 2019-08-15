<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Checkout\Confirm;

use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Payment\PaymentMethodEntity;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

class CheckoutConfirmPageLoader
{
    /**
     * @var SalesChannelRepositoryInterface
     */
    private $paymentMethodRepository;

    /**
     * @var SalesChannelRepositoryInterface
     */
    private $shippingMethodRepository;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var CartService
     */
    private $cartService;

    public function __construct(
        SalesChannelRepositoryInterface $paymentMethodRepository,
        SalesChannelRepositoryInterface $shippingMethodRepository,
        EventDispatcherInterface $eventDispatcher,
        CartService $cartService
    ) {
        $this->paymentMethodRepository = $paymentMethodRepository;
        $this->shippingMethodRepository = $shippingMethodRepository;
        $this->eventDispatcher = $eventDispatcher;
        $this->cartService = $cartService;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    public function load(Request $request, SalesChannelContext $salesChannelContext): CheckoutConfirmPage
    {
        $page = new CheckoutConfirmPage(
            $this->getPaymentMethods($salesChannelContext),
            $this->getShippingMethods($salesChannelContext)
        );

        $page->setCart($this->cartService->getCart($salesChannelContext->getToken(), $salesChannelContext));

        $this->eventDispatcher->dispatch(
            new CheckoutConfirmPageLoadedEvent($page, $salesChannelContext, $request)
        );

        return $page;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getPaymentMethods(SalesChannelContext $salesChannelContext): PaymentMethodCollection
    {
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

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
        $criteria = (new Criteria())
            ->addFilter(new EqualsFilter('active', true))
            ->addAssociation('media');

        /** @var ShippingMethodCollection $shippingMethods */
        $shippingMethods = $this->shippingMethodRepository->search($criteria, $salesChannelContext)->getEntities();

        return $shippingMethods->filterByActiveRules($salesChannelContext);
    }
}

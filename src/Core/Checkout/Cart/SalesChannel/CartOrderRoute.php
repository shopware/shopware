<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartPersisterInterface;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedCriteriaEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\Exception\InvalidOrderException;
use Shopware\Core\Checkout\Payment\PreparedPaymentService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class CartOrderRoute extends AbstractCartOrderRoute
{
    private CartCalculator $cartCalculator;

    private EntityRepository $orderRepository;

    private OrderPersisterInterface $orderPersister;

    private CartPersisterInterface $cartPersister;

    private EventDispatcherInterface $eventDispatcher;

    private PreparedPaymentService $preparedPaymentService;

    /**
     * @internal
     */
    public function __construct(
        CartCalculator $cartCalculator,
        EntityRepository $orderRepository,
        OrderPersisterInterface $orderPersister,
        CartPersisterInterface $cartPersister,
        EventDispatcherInterface $eventDispatcher,
        PreparedPaymentService $preparedPaymentService
    ) {
        $this->cartCalculator = $cartCalculator;
        $this->orderRepository = $orderRepository;
        $this->orderPersister = $orderPersister;
        $this->cartPersister = $cartPersister;
        $this->eventDispatcher = $eventDispatcher;
        $this->preparedPaymentService = $preparedPaymentService;
    }

    public function getDecorated(): AbstractCartOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.3.0.0")
     * @Route("/store-api/checkout/order", name="store-api.checkout.cart.order", methods={"POST"}, defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true})
     */
    public function order(Cart $cart, SalesChannelContext $context, RequestDataBag $data): CartOrderRouteResponse
    {
        // we use this state in stock updater class, to prevent duplicate available stock updates
        $context->addState('checkout-order-route');

        $calculatedCart = $this->cartCalculator->calculate($cart, $context);

        $this->addCustomerComment($calculatedCart, $data);
        $this->addAffiliateTracking($calculatedCart, $data);

        $preOrderPayment = Profiler::trace('checkout-order::pre-payment', function () use ($calculatedCart, $data, $context) {
            return $this->preparedPaymentService->handlePreOrderPayment($calculatedCart, $data, $context);
        });

        $orderId = Profiler::trace('checkout-order::order-persist', function () use ($calculatedCart, $context) {
            return $this->orderPersister->persist($calculatedCart, $context);
        });

        $criteria = new Criteria([$orderId]);
        $criteria->setTitle('order-route::order-loading');
        $criteria
            ->addAssociation('orderCustomer.customer')
            ->addAssociation('orderCustomer.salutation')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('lineItems.cover')
            ->addAssociation('currency')
            ->addAssociation('addresses.country')
            ->addAssociation('addresses.countryState')
            ->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        $this->eventDispatcher->dispatch(new CheckoutOrderPlacedCriteriaEvent($criteria, $context));

        /** @var OrderEntity|null $orderEntity */
        $orderEntity = Profiler::trace('checkout-order::order-loading', function () use ($criteria, $context) {
            return $this->orderRepository->search($criteria, $context->getContext())->first();
        });

        if (!$orderEntity) {
            throw new InvalidOrderException($orderId);
        }

        $event = new CheckoutOrderPlacedEvent(
            $context->getContext(),
            $orderEntity,
            $context->getSalesChannel()->getId()
        );

        Profiler::trace('checkout-order::event-listeners', function () use ($event): void {
            $this->eventDispatcher->dispatch($event);
        });

        $this->cartPersister->delete($context->getToken(), $context);

        Profiler::trace('checkout-order::post-payment', function () use ($orderEntity, $data, $context, $preOrderPayment): void {
            $this->preparedPaymentService->handlePostOrderPayment($orderEntity, $data, $context, $preOrderPayment);
        });

        return new CartOrderRouteResponse($orderEntity);
    }

    private function addCustomerComment(Cart $cart, DataBag $data): void
    {
        $customerComment = ltrim(rtrim((string) $data->get(OrderService::CUSTOMER_COMMENT_KEY, '')));

        if ($customerComment === '') {
            return;
        }

        $cart->setCustomerComment($customerComment);
    }

    private function addAffiliateTracking(Cart $cart, DataBag $data): void
    {
        $affiliateCode = $data->get(OrderService::AFFILIATE_CODE_KEY);
        $campaignCode = $data->get(OrderService::CAMPAIGN_CODE_KEY);
        if ($affiliateCode) {
            $cart->setAffiliateCode($affiliateCode);
        }

        if ($campaignCode) {
            $cart->setCampaignCode($campaignCode);
        }
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\SalesChannel;

use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\CartContextHasher;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedCriteriaEvent;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\Order\OrderPersisterInterface;
use Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor;
use Shopware\Core\Checkout\Gateway\SalesChannel\AbstractCheckoutGatewayRoute;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Order\SalesChannel\OrderService;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Checkout\Payment\PaymentProcessor;
use Shopware\Core\Checkout\Payment\PreparedPaymentService;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Validation\DataBag\DataBag;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\Profiling\Profiler;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('checkout')]
class CartOrderRoute extends AbstractCartOrderRoute
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CartCalculator $cartCalculator,
        private readonly EntityRepository $orderRepository,
        private readonly OrderPersisterInterface $orderPersister,
        private readonly AbstractCartPersister $cartPersister,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly PreparedPaymentService $preparedPaymentService,
        private readonly PaymentProcessor $paymentProcessor,
        private readonly TaxProviderProcessor $taxProviderProcessor,
        private readonly AbstractCheckoutGatewayRoute $checkoutGatewayRoute,
        private readonly CartContextHasher $cartContextHasher,
    ) {
    }

    public function getDecorated(): AbstractCartOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/checkout/order', name: 'store-api.checkout.cart.order', methods: ['POST'], defaults: ['_loginRequired' => true, '_loginRequiredAllowGuest' => true])]
    public function order(Cart $cart, SalesChannelContext $context, RequestDataBag $data): CartOrderRouteResponse
    {
        $hash = $data->getAlnum('hash');

        if ($hash && !$this->cartContextHasher->isMatching($hash, $cart, $context)) {
            throw CartException::hashMismatch($cart->getToken());
        }

        // we use this state in stock updater class, to prevent duplicate available stock updates
        $context->addState('checkout-order-route');

        $calculatedCart = $this->cartCalculator->calculate($cart, $context);

        $response = $this->checkoutGatewayRoute->load(new Request($data->all(), $data->all()), $cart, $context);
        $calculatedCart->addErrors(...$response->getErrors());

        $this->taxProviderProcessor->process($calculatedCart, $context);

        $this->addCustomerComment($calculatedCart, $data);
        $this->addAffiliateTracking($calculatedCart, $data);

        $preOrderPayment = Profiler::trace('checkout-order::pre-payment', fn () => $this->paymentProcessor->validate($calculatedCart, $data, $context));

        $orderId = Profiler::trace('checkout-order::order-persist', fn () => $this->orderPersister->persist($calculatedCart, $context));

        $criteria = new Criteria([$orderId]);
        $criteria
            ->setTitle('order-route::order-loading')
            ->addAssociation('orderCustomer.customer')
            ->addAssociation('orderCustomer.salutation')
            ->addAssociation('deliveries.shippingMethod')
            ->addAssociation('deliveries.shippingOrderAddress.country')
            ->addAssociation('deliveries.shippingOrderAddress.countryState')
            ->addAssociation('transactions.paymentMethod')
            ->addAssociation('lineItems.cover')
            ->addAssociation('lineItems.downloads.media')
            ->addAssociation('currency')
            ->addAssociation('addresses.country')
            ->addAssociation('addresses.countryState')
            ->addAssociation('stateMachineState')
            ->addAssociation('deliveries.stateMachineState')
            ->addAssociation('transactions.stateMachineState')
            ->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        $this->eventDispatcher->dispatch(new CheckoutOrderPlacedCriteriaEvent($criteria, $context));

        /** @var OrderEntity|null $orderEntity */
        $orderEntity = Profiler::trace('checkout-order::order-loading', fn () => $this->orderRepository->search($criteria, $context->getContext())->first());

        if (!$orderEntity) {
            throw CartException::invalidPaymentOrderNotStored($orderId);
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

        // @deprecated tag:v6.7.0 - remove post payment completely
        if (!Feature::isActive('v6.7.0.0')) {
            try {
                Profiler::trace('checkout-order::post-payment', function () use ($orderEntity, $data, $context, $preOrderPayment): void {
                    $this->preparedPaymentService->handlePostOrderPayment($orderEntity, $data, $context, $preOrderPayment);
                });
            } catch (PaymentException) {
                throw CartException::invalidPaymentButOrderStored($orderId);
            }
        }

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

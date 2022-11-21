<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedCriteriaEvent;
use Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent;
use Shopware\Core\Checkout\Order\Exception\PaymentMethodNotChangeableException;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\InvalidTransactionException;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\Exception\IllegalTransitionException;
use Shopware\Core\System\StateMachine\Loader\InitialStateIdLoader;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route(defaults={"_routeScope"={"store-api"}})
 */
class SetPaymentOrderRoute extends AbstractSetPaymentOrderRoute
{
    private EntityRepository $orderRepository;

    private AbstractPaymentMethodRoute $paymentRoute;

    private OrderService $orderService;

    private OrderConverter $orderConverter;

    private CartRuleLoader $cartRuleLoader;

    private EventDispatcherInterface $eventDispatcher;

    private InitialStateIdLoader $initialStateIdLoader;

    /**
     * @internal
     */
    public function __construct(
        OrderService $orderService,
        EntityRepository $orderRepository,
        AbstractPaymentMethodRoute $paymentRoute,
        OrderConverter $orderConverter,
        CartRuleLoader $cartRuleLoader,
        EventDispatcherInterface $eventDispatcher,
        InitialStateIdLoader $initialStateIdLoader
    ) {
        $this->orderService = $orderService;
        $this->orderRepository = $orderRepository;
        $this->paymentRoute = $paymentRoute;
        $this->orderConverter = $orderConverter;
        $this->cartRuleLoader = $cartRuleLoader;
        $this->eventDispatcher = $eventDispatcher;
        $this->initialStateIdLoader = $initialStateIdLoader;
    }

    public function getDecorated(): AbstractSetPaymentOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @Route(path="/store-api/order/payment", name="store-api.order.set-payment", methods={"POST"}, defaults={"_loginRequired"=true, "_loginRequiredAllowGuest"=true})
     */
    public function setPayment(Request $request, SalesChannelContext $context): SetPaymentOrderRouteResponse
    {
        $paymentMethodId = (string) $request->request->get('paymentMethodId');

        $orderId = (string) $request->request->get('orderId');
        $order = $this->loadOrder($orderId, $context);

        $context = $this->orderConverter->assembleSalesChannelContext(
            $order,
            $context->getContext(),
            [SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId]
        );

        $this->validateRequest($context, $paymentMethodId);

        $this->validatePaymentState($order);

        $this->setPaymentMethod($paymentMethodId, $order, $context);

        return new SetPaymentOrderRouteResponse();
    }

    private function setPaymentMethod(string $paymentMethodId, OrderEntity $order, SalesChannelContext $salesChannelContext): void
    {
        $context = $salesChannelContext->getContext();

        if ($this->tryTransition($order, $paymentMethodId, $context)) {
            return;
        }

        $initialState = $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE);

        $transactionAmount = new CalculatedPrice(
            $order->getPrice()->getTotalPrice(),
            $order->getPrice()->getTotalPrice(),
            $order->getPrice()->getCalculatedTaxes(),
            $order->getPrice()->getTaxRules()
        );

        $transactionId = Uuid::randomHex();
        $payload = [
            'id' => $order->getId(),
            'transactions' => [
                [
                    'id' => $transactionId,
                    'paymentMethodId' => $paymentMethodId,
                    'stateId' => $initialState,
                    'amount' => $transactionAmount,
                ],
            ],
            'ruleIds' => $this->getOrderRules($order, $salesChannelContext),
        ];

        $context->scope(
            Context::SYSTEM_SCOPE,
            function () use ($payload, $context): void {
                $this->orderRepository->update([$payload], $context);
            }
        );

        $changedOrder = $this->loadOrder($order->getId(), $salesChannelContext);
        $transactions = $changedOrder->getTransactions();
        if ($transactions === null || ($transaction = $transactions->get($transactionId)) === null) {
            throw new InvalidTransactionException($transactionId);
        }

        $event = new OrderPaymentMethodChangedEvent(
            $changedOrder,
            $transaction,
            $context,
            $salesChannelContext->getSalesChannelId()
        );
        $this->eventDispatcher->dispatch($event);
    }

    private function validateRequest(SalesChannelContext $salesChannelContext, string $paymentMethodId): void
    {
        $paymentRequest = new Request();
        $paymentRequest->query->set('onlyAvailable', '1');

        $availablePayments = $this->paymentRoute->load($paymentRequest, $salesChannelContext, new Criteria());

        if ($availablePayments->getPaymentMethods()->get($paymentMethodId) === null) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }
    }

    private function tryTransition(OrderEntity $order, string $paymentMethodId, Context $context): bool
    {
        $transactions = $order->getTransactions();
        if ($transactions === null || $transactions->count() < 1) {
            return false;
        }

        /** @var OrderTransactionEntity $lastTransaction */
        $lastTransaction = $transactions->last();

        foreach ($transactions as $transaction) {
            if ($transaction->getPaymentMethodId() === $paymentMethodId && $lastTransaction->getId() === $transaction->getId()) {
                $initialState = $this->initialStateIdLoader->get(OrderTransactionStates::STATE_MACHINE);
                if ($transaction->getStateId() === $initialState) {
                    return true;
                }

                try {
                    $this->orderService->orderTransactionStateTransition(
                        $transaction->getId(),
                        StateMachineTransitionActions::ACTION_REOPEN,
                        new ParameterBag(),
                        $context
                    );

                    return true;
                } catch (IllegalTransitionException $exception) {
                    // if we can't reopen the last transaction with a matching payment method
                    // we have to create a new transaction and cancel the previous one
                }
            }

            if ($transaction->getStateMachineState() !== null
                && ($transaction->getStateMachineState()->getTechnicalName() === OrderTransactionStates::STATE_CANCELLED
                    || $transaction->getStateMachineState()->getTechnicalName() === OrderTransactionStates::STATE_FAILED)
            ) {
                continue;
            }

            $context->scope(
                Context::SYSTEM_SCOPE,
                function () use ($transaction, $context): void {
                    $this->orderService->orderTransactionStateTransition(
                        $transaction->getId(),
                        StateMachineTransitionActions::ACTION_CANCEL,
                        new ParameterBag(),
                        $context
                    );
                }
            );
        }

        return false;
    }

    /**
     * @return string[]
     */
    private function getOrderRules(OrderEntity $order, SalesChannelContext $salesChannelContext): array
    {
        $convertedCart = $this->orderConverter->convertToCart($order, $salesChannelContext->getContext());
        $ruleIds = $this->cartRuleLoader->loadByCart(
            $salesChannelContext,
            $convertedCart,
            new CartBehavior($salesChannelContext->getPermissions())
        )->getMatchingRules()->getIds();

        return array_values($ruleIds);
    }

    private function loadOrder(string $orderId, SalesChannelContext $context): OrderEntity
    {
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions');
        $criteria->getAssociation('transactions')->addSorting(new FieldSorting('createdAt'));

        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();

        $criteria->addFilter(
            new EqualsFilter(
                'order.orderCustomer.customerId',
                $customer->getId()
            )
        );
        $criteria->addAssociations(['lineItems', 'deliveries', 'orderCustomer', 'tags']);

        $this->eventDispatcher->dispatch(new OrderPaymentMethodChangedCriteriaEvent($orderId, $criteria, $context));

        /** @var OrderEntity|null $order */
        $order = $this->orderRepository->search($criteria, $context->getContext())->first();

        if ($order === null) {
            throw new EntityNotFoundException('order', $orderId);
        }

        return $order;
    }

    /**
     * @throws PaymentMethodNotChangeableException
     */
    private function validatePaymentState(OrderEntity $order): void
    {
        if ($this->orderService->isPaymentChangeableByTransactionState($order)) {
            return;
        }

        throw new PaymentMethodNotChangeableException($order->getId());
    }
}

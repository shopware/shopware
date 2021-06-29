<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartRuleLoader;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\SalesChannel\AbstractPaymentMethodRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\LoginRequired;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextService;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class SetPaymentOrderRoute extends AbstractSetPaymentOrderRoute
{
    private EntityRepositoryInterface $orderRepository;

    private AbstractPaymentMethodRoute $paymentRoute;

    private StateMachineRegistry $stateMachineRegistry;

    private OrderService $orderService;

    private OrderConverter $orderConverter;

    private CartRuleLoader $cartRuleLoader;

    public function __construct(
        OrderService $orderService,
        EntityRepositoryInterface $orderRepository,
        AbstractPaymentMethodRoute $paymentRoute,
        StateMachineRegistry $stateMachineRegistry,
        OrderConverter $orderConverter,
        CartRuleLoader $cartRuleLoader
    ) {
        $this->orderService = $orderService;
        $this->orderRepository = $orderRepository;
        $this->paymentRoute = $paymentRoute;
        $this->stateMachineRegistry = $stateMachineRegistry;
        $this->orderConverter = $orderConverter;
        $this->cartRuleLoader = $cartRuleLoader;
    }

    public function getDecorated(): AbstractSetPaymentOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/order/payment",
     *      summary="Update the payment method of an order",
     *      description="Changes the payment method of a specific order. You can use the /order route to find out if the payment method of an order can be changed - take a look at the `paymentChangeable`- array in the response.",
     *      operationId="orderSetPayment",
     *      tags={"Store API", "Order"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              required={
     *                  "paymentMethodId",
     *                  "orderId"
     *              },
     *              @OA\Property(
     *                  property="paymentMethodId",
     *                  description="The identifier of the paymentMethod to be set",
     *                  type="string"
     *              ),
     *              @OA\Property(
     *                  property="orderId",
     *                  description="The identifier of the order.",
     *                  type="string"
     *              )
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Successfully updated the payment method of the order.",
     *          @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
     *     )
     * )
     * @LoginRequired(allowGuest=true)
     * @Route(path="/store-api/order/payment", name="store-api.order.set-payment", methods={"POST"})
     */
    public function setPayment(Request $request, SalesChannelContext $context): SetPaymentOrderRouteResponse
    {
        $paymentMethodId = $request->get('paymentMethodId');

        $criteria = new Criteria([$request->get('orderId')]);
        $criteria->addAssociation('transactions');

        /** @var CustomerEntity $customer */
        $customer = $context->getCustomer();

        $criteria->addFilter(
            new EqualsFilter(
                'order.orderCustomer.customerId',
                $customer->getId()
            )
        );
        $criteria->addAssociations(['lineItems', 'deliveries']);

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context->getContext())->first();

        $overrideOptions = [SalesChannelContextService::PAYMENT_METHOD_ID => $paymentMethodId];
        $context = $this->orderConverter->assembleSalesChannelContext($order, $context->getContext(), $overrideOptions);

        $this->validateRequest($context, $paymentMethodId);

        $this->setPaymentMethod($paymentMethodId, $order, $context);

        return new SetPaymentOrderRouteResponse();
    }

    private function setPaymentMethod(string $paymentMethodId, OrderEntity $order, SalesChannelContext $salesChannelContext): void
    {
        $context = $salesChannelContext->getContext();

        if ($this->tryTransition($order, $paymentMethodId, $context)) {
            return;
        }

        $initialState = $this->stateMachineRegistry->getInitialState(
            OrderTransactionStates::STATE_MACHINE,
            $context
        );

        $transactionAmount = new CalculatedPrice(
            $order->getPrice()->getTotalPrice(),
            $order->getPrice()->getTotalPrice(),
            $order->getPrice()->getCalculatedTaxes(),
            $order->getPrice()->getTaxRules()
        );

        $payload = [
            'id' => $order->getId(),
            'transactions' => [
                [
                    'id' => Uuid::randomHex(),
                    'paymentMethodId' => $paymentMethodId,
                    'stateId' => $initialState->getId(),
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

    private function isSamePaymentMethod(OrderTransactionEntity $transaction, string $paymentMethodId): bool
    {
        if ($transaction->getStateMachineState() === null
            || $transaction->getStateMachineState()->getTechnicalName() === OrderTransactionStates::STATE_CANCELLED
        ) {
            return false;
        }

        return $transaction->getPaymentMethodId() === $paymentMethodId;
    }

    private function tryTransition(OrderEntity $order, string $paymentMethodId, Context $context): bool
    {
        $transactions = $order->getTransactions();
        if ($transactions === null || $transactions->count() < 1) {
            return false;
        }

        foreach ($transactions as $transaction) {
            if ($this->isSamePaymentMethod($transaction, $paymentMethodId)) {
                return true;
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
}

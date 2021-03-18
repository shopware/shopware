<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Customer\CustomerEntity;
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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"store-api"})
 */
class SetPaymentOrderRoute extends AbstractSetPaymentOrderRoute
{
    /**
     * @var EntityRepositoryInterface
     */
    private $orderRepository;

    /**
     * @var AbstractPaymentMethodRoute
     */
    private $paymentRoute;

    /**
     * @var StateMachineRegistry
     */
    private $stateMachineRegistry;

    /**
     * @var OrderService
     */
    private $orderService;

    public function __construct(
        OrderService $orderService,
        EntityRepositoryInterface $orderRepository,
        AbstractPaymentMethodRoute $paymentRoute,
        StateMachineRegistry $stateMachineRegistry
    ) {
        $this->orderService = $orderService;
        $this->orderRepository = $orderRepository;
        $this->paymentRoute = $paymentRoute;
        $this->stateMachineRegistry = $stateMachineRegistry;
    }

    public function getDecorated(): AbstractSetPaymentOrderRoute
    {
        throw new DecorationPatternException(self::class);
    }

    /**
     * @Since("6.2.0.0")
     * @OA\Post(
     *      path="/order/payment",
     *      summary="set payment for an order",
     *      operationId="orderSetPayment",
     *      tags={"Store API", "Account"},
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(
     *              @OA\Property(property="paymentMethodId", description="The id of the paymentMethod to be set", type="string"),
     *              @OA\Property(property="orderId", description="The id of the order", type="string")
     *          )
     *      ),
     *      @OA\Response(
     *          response="200",
     *          description="Successfully set a payment",
     *          @OA\JsonContent(ref="#/components/schemas/SuccessResponse")
     *     )
     * )
     * @LoginRequired()
     * @Route(path="/store-api/order/payment", name="store-api.order.set-payment", methods={"POST"})
     */
    public function setPayment(Request $request, SalesChannelContext $context): SetPaymentOrderRouteResponse
    {
        $paymentMethodId = $request->get('paymentMethodId');

        $this->validateRequest($context, $paymentMethodId);

        $this->setPaymentMethod($paymentMethodId, $request->get('orderId'), $context);

        return new SetPaymentOrderRouteResponse();
    }

    private function setPaymentMethod(string $paymentMethodId, string $orderId, SalesChannelContext $salesChannelContext): void
    {
        $context = $salesChannelContext->getContext();
        $initialState = $this->stateMachineRegistry->getInitialState(
            OrderTransactionStates::STATE_MACHINE,
            $context
        );

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions');

        /** @var CustomerEntity $customer */
        $customer = $salesChannelContext->getCustomer();

        $criteria->addFilter(
            new EqualsFilter(
                'order.orderCustomer.customerId',
                $customer->getId()
            )
        );

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->first();

        $context->scope(
            Context::SYSTEM_SCOPE,
            function () use ($order, $initialState, $orderId, $paymentMethodId, $context): void {
                if ($order->getTransactions() !== null && $order->getTransactions()->count() >= 1) {
                    foreach ($order->getTransactions() as $transaction) {
                        if ($transaction->getStateMachineState()->getTechnicalName() !== OrderTransactionStates::STATE_CANCELLED) {
                            $this->orderService->orderTransactionStateTransition(
                                $transaction->getId(),
                                'cancel',
                                new ParameterBag(),
                                $context
                            );
                        }
                    }
                }
                $transactionId = Uuid::randomHex();
                $transactionAmount = new CalculatedPrice(
                    $order->getPrice()->getTotalPrice(),
                    $order->getPrice()->getTotalPrice(),
                    $order->getPrice()->getCalculatedTaxes(),
                    $order->getPrice()->getTaxRules()
                );

                $this->orderRepository->update([
                    [
                        'id' => $orderId,
                        'transactions' => [
                            [
                                'id' => $transactionId,
                                'paymentMethodId' => $paymentMethodId,
                                'stateId' => $initialState->getId(),
                                'amount' => $transactionAmount,
                            ],
                        ],
                    ],
                ], $context);
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
}

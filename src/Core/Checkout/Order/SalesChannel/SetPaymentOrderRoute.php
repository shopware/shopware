<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\SalesChannel;

use OpenApi\Annotations as OA;
use Shopware\Core\Checkout\Cart\Exception\CustomerNotLoggedInException;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Exception\UnknownPaymentMethodException;
use Shopware\Core\Checkout\Payment\SalesChannel\PaymentMethodRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
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
     * @var PaymentMethodRoute
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
        PaymentMethodRoute $paymentRoute,
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
     * @OA\Post(
     *      path="/order/set-payment",
     *      description="set payment for an order",
     *      operationId="orderSetPayment",
     *      tags={"Store API", "Account"},
     *      @OA\Parameter(
     *          name="paymentMethodId",
     *          in="post",
     *          required=true,
     *          description="The id of the paymentMethod to be set",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Parameter(
     *          name="orderId",
     *          in="post",
     *          required=true,
     *          description="The id of the order",
     *          @OA\Schema(
     *              type="string"
     *          )
     *      ),
     *      @OA\Response(
     *          response="200"
     *     )
     * )
     * @Route(path="/store-api/v{version}/order/payment", name="store-api.order.set-payment", methods={"POST"})
     */
    public function setPayment(Request $request, SalesChannelContext $salesChannelContext): SetPaymentOrderRouteResponse
    {
        $paymentMethodId = $request->get('paymentMethodId');

        $this->validateRequest($salesChannelContext, $paymentMethodId);

        $this->setPaymentMethod($paymentMethodId, $request->get('orderId'), $salesChannelContext);

        return new SetPaymentOrderRouteResponse();
    }

    public function setPaymentMethod(string $paymentMethodId, string $orderId, SalesChannelContext $salesChannelContext): void
    {
        $initialState = $this->stateMachineRegistry->getInitialState(OrderTransactionStates::STATE_MACHINE, $salesChannelContext->getContext());

        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('transactions');

        if ($salesChannelContext->getCustomer() === null) {
            throw new CustomerNotLoggedInException();
        }
        $criteria->addFilter(
            new EqualsFilter(
                'order.orderCustomer.customerId',
                $salesChannelContext->getCustomer()->getId()
            )
        );

        /** @var OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $salesChannelContext->getContext())->first();

        $salesChannelContext->getContext()->scope(
            Context::SYSTEM_SCOPE,
            function () use ($order, $initialState, $orderId, $paymentMethodId, $salesChannelContext): void {
                if ($order->getTransactions() !== null && $order->getTransactions()->count() >= 1) {
                    foreach ($order->getTransactions() as $transaction) {
                        if ($transaction->getStateMachineState()->getTechnicalName() !== OrderTransactionStates::STATE_CANCELLED) {
                            $this->orderService->orderTransactionStateTransition(
                                $transaction->getId(),
                                'cancel',
                                new ParameterBag(),
                                $salesChannelContext->getContext()
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
                ], $salesChannelContext->getContext());
            }
        );
    }

    private function validateRequest(SalesChannelContext $salesChannelContext, $paymentMethodId): void
    {
        $paymentRequest = new Request();
        $paymentRequest->query->set('onlyAvailable', 1);

        $availablePayments = $this->paymentRoute->load($paymentRequest, $salesChannelContext);

        if ($availablePayments->getPaymentMethods()->get($paymentMethodId) === null) {
            throw new UnknownPaymentMethodException($paymentMethodId);
        }
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Handler;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Order\OrderConverter;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\Recurring\RecurringDataStruct;
use Shopware\Core\Checkout\Payment\Cart\RefundPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Payment\AppPaymentException;
use Shopware\Core\Framework\App\Payment\Payload\PaymentPayloadService;
use Shopware\Core\Framework\App\Payment\Payload\Struct\PaymentPayload;
use Shopware\Core\Framework\App\Payment\Payload\Struct\RefundPayload;
use Shopware\Core\Framework\App\Payment\Payload\Struct\SourcedPayloadInterface;
use Shopware\Core\Framework\App\Payment\Payload\Struct\ValidatePayload;
use Shopware\Core\Framework\App\Payment\Response\AbstractResponse;
use Shopware\Core\Framework\App\Payment\Response\AsyncFinalizeResponse;
use Shopware\Core\Framework\App\Payment\Response\PaymentResponse;
use Shopware\Core\Framework\App\Payment\Response\RecurringPayResponse;
use Shopware\Core\Framework\App\Payment\Response\RefundResponse;
use Shopware\Core\Framework\App\Payment\Response\ValidateResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AppPaymentHandler extends AbstractPaymentHandler
{
    public function __construct(
        private readonly StateMachineRegistry $stateMachineRegistry,
        private readonly PaymentPayloadService $payloadService,
        private readonly EntityRepository $refundRepository,
        private readonly EntityRepository $orderTransactionRepository,
        private readonly OrderConverter $orderConverter,
    ) {
    }

    public function supports(Context $context): array
    {
        return [PaymentHandlerType::REFUND, PaymentHandlerType::REFUND];
    }

    public function validate(Request $request, Cart $cart, SalesChannelContext $context): Struct
    {
        $appPaymentMethod = $context->getPaymentMethod()->getAppPaymentMethod();
        if ($appPaymentMethod === null) {
            throw PaymentException::validatePreparedPaymentInterrupted('Loaded data invalid');
        }

        $validateUrl = $appPaymentMethod->getValidateUrl();
        if (!$validateUrl) {
            return new ArrayStruct();
        }

        $app = $this->getApp($appPaymentMethod);

        $payload = $this->buildValidatePayload($cart, $request, $context);
        $response = $this->requestAppServer($validateUrl, ValidateResponse::class, $payload, $app, $context->getContext());

        return new ArrayStruct($response->getPreOrderPayment());
    }

    public function pay(Request $request, PaymentTransactionStruct $transaction, Context $context, ?Struct $validateStruct = null): ?RedirectResponse
    {
        $orderTransaction = $this->getOrderTransaction($transaction->getOrderTransactionId(), $context);
        $appPaymentMethod = $this->getAppPaymentMethod($orderTransaction);
        $app = $this->getApp($appPaymentMethod);

        $payload = $this->buildPayload($orderTransaction, $orderTransaction->getOrder(), $request->request->all(), $transaction->getReturnUrl(), new ArrayStruct(), $transaction->getRecurring());

        $captureUrl = $appPaymentMethod->getCaptureUrl();
        if ($captureUrl) {
            $response = $this->requestAppServer($captureUrl, PaymentResponse::class, $payload, $app, $context);
            $this->transitionOrderTransaction($orderTransaction->getId(), $response, $context);
        }

        $payUrl = $appPaymentMethod->getPayUrl();
        if ($payUrl) {
            /** @var PaymentResponse $response */
            $response = $this->requestAppServer($payUrl, PaymentResponse::class, $payload, $app, $context);
            $this->transitionOrderTransaction($orderTransaction->getId(), $response, $context);

            if ($response->getRedirectUrl()) {
                return new RedirectResponse($response->getRedirectUrl());
            }
        }

        return null;
    }

    public function finalize(Request $request, PaymentTransactionStruct $transaction, Context $context): void
    {
        $queryParameters = $request->query->all();

        unset($queryParameters['_sw_payment_token']);

        $orderTransaction = $this->getOrderTransaction($transaction->getOrderTransactionId(), $context);
        $paymentMethod = $this->getAppPaymentMethod($orderTransaction);
        $app = $this->getApp($paymentMethod);

        $payload = $this->buildPayload($orderTransaction, $orderTransaction->getOrder(), $queryParameters, recurring: $transaction->getRecurring());

        $url = $paymentMethod->getFinalizeUrl();
        if ($url === null) {
            throw AppPaymentException::interrupted('Finalize URL not defined');
        }

        $response = $this->requestAppServer($url, AsyncFinalizeResponse::class, $payload, $app, $context);
        $this->transitionOrderTransaction($orderTransaction->getId(), $response, $context);
    }

    public function refund(Request $request, RefundPaymentTransactionStruct $transaction, Context $context): void
    {
        $criteria = new Criteria([$transaction->getRefundId()]);
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('transactionCapture.transaction.order');
        $criteria->addAssociation('transactionCapture.transaction.paymentMethod.appPaymentMethod.app');
        $criteria->addAssociation('transactionCapture.positions');

        /** @var OrderTransactionCaptureRefundEntity|null $refund */
        $refund = $this->refundRepository->search($criteria, $context)->first();

        if (!$refund) {
            throw PaymentException::unknownRefund($transaction->getRefundId());
        }

        if (!$refund->getTransactionCapture()?->getTransaction()?->getOrder()) {
            return;
        }

        $transaction = $refund->getTransactionCapture()->getTransaction();
        $paymentMethod = $this->getAppPaymentMethod($transaction);
        $app = $this->getApp($paymentMethod);

        $refundUrl = $paymentMethod->getRefundUrl();
        if (!$refundUrl) {
            return;
        }

        $payload = $this->buildRefundPayload($refund, $refund->getTransactionCapture()->getTransaction()->getOrder());
        $response = $this->requestAppServer($refundUrl, RefundResponse::class, $payload, $app, $context);

        $this->transitionOrderTransaction($transaction->getId(), $response, $context);
    }

    public function recurring(PaymentTransactionStruct $transaction, Context $context): void
    {
        $orderTransaction = $this->getOrderTransaction($transaction->getOrderTransactionId(), $context);
        $paymentMethod = $this->getAppPaymentMethod($orderTransaction);
        $app = $this->getApp($paymentMethod);

        $recurringUrl = $paymentMethod->getRecurringUrl();
        if (!$recurringUrl) {
            return;
        }

        $payload = $this->buildPayload($orderTransaction, $orderTransaction->getOrder(), recurring: $transaction->getRecurring());
        $response = $this->requestAppServer($recurringUrl, RecurringPayResponse::class, $payload, $app, $context);

        $this->transitionOrderTransaction($orderTransaction->getId(), $response, $context);
    }

    /**
     * @template T of AbstractResponse
     *
     * @param class-string<AbstractResponse> $responseClass
     *
     * @return T
     */
    private function requestAppServer(
        string $url,
        string $responseClass,
        SourcedPayloadInterface $payload,
        AppEntity $app,
        Context $context
    ): AbstractResponse {
        try {
            $response = $this->payloadService->request($url, $payload, $app, $responseClass, $context);
        } catch (\Throwable $exception) {
            throw AppPaymentException::interrupted($exception->getMessage());
        }

        if ($response->getMessage() || $response->getStatus() === StateMachineTransitionActions::ACTION_FAIL) {
            throw AppPaymentException::interrupted($response->getMessage() ?? 'Payment was reported as failed.');
        }

        return $response;
    }

    private function transitionOrderTransaction(string $orderTransactionId, AbstractResponse $response, Context $context): void
    {
        if ($response instanceof PaymentResponse && $response->getStatus()) {
            $this->stateMachineRegistry->transition(
                new Transition(
                    OrderTransactionDefinition::ENTITY_NAME,
                    $orderTransactionId,
                    $response->getStatus(),
                    'stateId'
                ),
                $context
            );
        }
    }

    private function getOrderTransaction(string $orderTransactionId, Context $context): OrderTransactionEntity
    {
        $criteria = new Criteria([$orderTransactionId]);
        $criteria->addAssociation('order');
        $criteria->addAssociation('paymentMethod.appPaymentMethod.app');

        /** @var OrderTransactionEntity|null $orderTransaction */
        $orderTransaction = $this->orderTransactionRepository->search($criteria, $context)->first();

        if (!$orderTransaction) {
            throw AppPaymentException::invalidTransaction($orderTransactionId);
        }

        return $orderTransaction;
    }

    private function getAppPaymentMethod(OrderTransactionEntity $orderTransaction): AppPaymentMethodEntity
    {
        if ($orderTransaction->getPaymentMethod()?->getAppPaymentMethod() === null) {
            throw AppPaymentException::interrupted('Loaded data invalid');
        }

        return $orderTransaction->getPaymentMethod()->getAppPaymentMethod();
    }

    private function getApp(AppPaymentMethodEntity $appPaymentMethod): AppEntity
    {
        if (!$appPaymentMethod->getApp()) {
            throw AppPaymentException::interrupted('Loaded data invalid');
        }

        return $appPaymentMethod->getApp();
    }

    /**
     * @param array<string, mixed> $requestData
     */
    private function buildPayload(
        OrderTransactionEntity $transaction,
        OrderEntity $order,
        array $requestData = [],
        ?string $returnUrl = null,
        ?Struct $preOrderPayment = null,
        ?RecurringDataStruct $recurring = null
    ): PaymentPayload {
        return new PaymentPayload($transaction, $order, $requestData, $returnUrl, $preOrderPayment, $recurring);
    }

    protected function buildRefundPayload(OrderTransactionCaptureRefundEntity $refund, OrderEntity $order): RefundPayload
    {
        return new RefundPayload(
            $refund,
            $order
        );
    }

    protected function buildValidatePayload(Cart $cart, Request $request, SalesChannelContext $context): ValidatePayload
    {
        return new ValidatePayload(
            $cart,
            $request->request->all(),
            $context,
        );
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment\Handler;

use Psr\Http\Client\ClientExceptionInterface;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PreparedPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RecurringPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\RefundPaymentHandlerInterface;
use Shopware\Core\Checkout\Payment\Cart\PreparedPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\Cart\RecurringPaymentTransactionStruct;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\App\Aggregate\AppPaymentMethod\AppPaymentMethodEntity;
use Shopware\Core\Framework\App\Payment\Payload\PaymentPayloadService;
use Shopware\Core\Framework\App\Payment\Payload\Struct\CapturePayload;
use Shopware\Core\Framework\App\Payment\Payload\Struct\RecurringPayPayload;
use Shopware\Core\Framework\App\Payment\Payload\Struct\RefundPayload;
use Shopware\Core\Framework\App\Payment\Payload\Struct\ValidatePayload;
use Shopware\Core\Framework\App\Payment\Response\CaptureResponse;
use Shopware\Core\Framework\App\Payment\Response\RecurringPayResponse;
use Shopware\Core\Framework\App\Payment\Response\RefundResponse;
use Shopware\Core\Framework\App\Payment\Response\ValidateResponse;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionActions;
use Shopware\Core\System\StateMachine\StateMachineRegistry;
use Shopware\Core\System\StateMachine\Transition;

/**
 * @internal only for use by the app-system
 */
#[Package('core')]
class AppPaymentHandler implements RefundPaymentHandlerInterface, PreparedPaymentHandlerInterface, RecurringPaymentHandlerInterface
{
    public function __construct(
        protected OrderTransactionStateHandler $transactionStateHandler,
        protected StateMachineRegistry $stateMachineRegistry,
        protected PaymentPayloadService $payloadService,
        protected EntityRepository $refundRepository
    ) {
    }

    public function validate(Cart $cart, RequestDataBag $requestDataBag, SalesChannelContext $context): Struct
    {
        $appPaymentMethod = $context->getPaymentMethod()->getAppPaymentMethod();
        if ($appPaymentMethod === null) {
            throw PaymentException::validatePreparedPaymentInterrupted('Loaded data invalid');
        }

        $validateUrl = $appPaymentMethod->getValidateUrl();
        if (empty($validateUrl)) {
            return new ArrayStruct();
        }

        $payload = $this->buildValidatePayload($cart, $requestDataBag, $context);
        $app = $appPaymentMethod->getApp();
        if ($app === null) {
            throw PaymentException::validatePreparedPaymentInterrupted('App not defined');
        }

        try {
            $response = $this->payloadService->request($validateUrl, $payload, $app, ValidateResponse::class, $context->getContext());
        } catch (ClientExceptionInterface $exception) {
            throw PaymentException::validatePreparedPaymentInterrupted(sprintf('App error: %s', $exception->getMessage()));
        }

        if (!$response instanceof ValidateResponse) {
            throw PaymentException::validatePreparedPaymentInterrupted('Invalid app response');
        }

        if ($response->getMessage()) {
            throw PaymentException::validatePreparedPaymentInterrupted($response->getMessage());
        }

        return new ArrayStruct($response->getPreOrderPayment());
    }

    public function capture(PreparedPaymentTransactionStruct $transaction, RequestDataBag $requestDataBag, SalesChannelContext $context, Struct $preOrderPaymentStruct): void
    {
        $captureUrl = $this->getAppPaymentMethod($transaction->getOrderTransaction())->getCaptureUrl();
        if (empty($captureUrl)) {
            return;
        }

        $payload = $this->buildCapturePayload($transaction, $preOrderPaymentStruct);
        $app = $this->getAppPaymentMethod($transaction->getOrderTransaction())->getApp();
        if ($app === null) {
            throw PaymentException::capturePreparedException($transaction->getOrderTransaction()->getId(), 'App not defined');
        }

        try {
            $response = $this->payloadService->request($captureUrl, $payload, $app, CaptureResponse::class, $context->getContext());
        } catch (ClientExceptionInterface $exception) {
            throw PaymentException::capturePreparedException($transaction->getOrderTransaction()->getId(), sprintf('App error: %s', $exception->getMessage()));
        }

        if (!$response instanceof CaptureResponse) {
            throw PaymentException::capturePreparedException($transaction->getOrderTransaction()->getId(), 'Invalid app response');
        }

        if ($response->getMessage() || $response->getStatus() === StateMachineTransitionActions::ACTION_FAIL) {
            throw PaymentException::capturePreparedException($transaction->getOrderTransaction()->getId(), $response->getMessage() ?? 'Payment was reported as failed.');
        }

        if (empty($response->getStatus())) {
            return;
        }

        $this->stateMachineRegistry->transition(
            new Transition(
                OrderTransactionDefinition::ENTITY_NAME,
                $transaction->getOrderTransaction()->getId(),
                $response->getStatus(),
                'stateId'
            ),
            $context->getContext()
        );
    }

    public function refund(string $refundId, Context $context): void
    {
        $criteria = new Criteria([$refundId]);
        $criteria->addAssociation('stateMachineState');
        $criteria->addAssociation('transactionCapture.transaction.order');
        $criteria->addAssociation('transactionCapture.transaction.paymentMethod.appPaymentMethod.app');
        $criteria->addAssociation('transactionCapture.positions');

        $refund = $this->refundRepository->search($criteria, $context)->first();

        if (!($refund instanceof OrderTransactionCaptureRefundEntity)) {
            throw PaymentException::unknownRefund($refundId);
        }

        if (!$refund->getTransactionCapture()
            || !$refund->getTransactionCapture()->getTransaction()
            || !$refund->getTransactionCapture()->getTransaction()->getOrder()
        ) {
            return;
        }

        $transaction = $refund->getTransactionCapture()->getTransaction();
        $paymentMethod = $this->getAppPaymentMethod($transaction);
        $refundUrl = $paymentMethod->getRefundUrl();

        if (!$refundUrl) {
            return;
        }

        $app = $paymentMethod->getApp();

        if (!$app) {
            throw PaymentException::refundInterrupted($refund->getId(), 'App not defined');
        }

        $payload = $this->buildRefundPayload($refund, $refund->getTransactionCapture()->getTransaction()->getOrder());

        try {
            $response = $this->payloadService->request($refundUrl, $payload, $app, RefundResponse::class, $context);
        } catch (ClientExceptionInterface $exception) {
            throw PaymentException::refundInterrupted($refund->getId(), sprintf('App error: %s', $exception->getMessage()));
        }

        if (!$response instanceof RefundResponse) {
            throw PaymentException::refundInterrupted($refund->getId(), 'Invalid app response');
        }

        if ($response->getMessage() || $response->getStatus() === StateMachineTransitionActions::ACTION_FAIL) {
            throw PaymentException::refundInterrupted($refund->getId(), $response->getMessage() ?? 'Refund was reported as failed.');
        }

        if (empty($response->getStatus())) {
            return;
        }

        $this->stateMachineRegistry->transition(
            new Transition(
                OrderTransactionCaptureRefundDefinition::ENTITY_NAME,
                $refund->getId(),
                $response->getStatus(),
                'stateId'
            ),
            $context
        );
    }

    public function captureRecurring(RecurringPaymentTransactionStruct $transaction, Context $context): void
    {
        $recurringUrl = $this->getAppPaymentMethod($transaction->getOrderTransaction())->getRecurringUrl();

        if (empty($recurringUrl)) {
            return;
        }

        $payload = $this->buildRecurringPayload($transaction);
        $app = $this->getAppPaymentMethod($transaction->getOrderTransaction())->getApp();
        if ($app === null) {
            throw PaymentException::recurringInterrupted($transaction->getOrderTransaction()->getId(), 'App not defined');
        }

        try {
            $response = $this->payloadService->request($recurringUrl, $payload, $app, RecurringPayResponse::class, $context);
        } catch (ClientExceptionInterface $exception) {
            throw PaymentException::recurringInterrupted($transaction->getOrderTransaction()->getId(), sprintf('App error: %s', $exception->getMessage()));
        }

        if (!$response instanceof RecurringPayResponse) {
            throw PaymentException::recurringInterrupted($transaction->getOrderTransaction()->getId(), 'Invalid app response');
        }

        if ($response->getMessage() || $response->getStatus() === StateMachineTransitionActions::ACTION_FAIL) {
            throw PaymentException::recurringInterrupted($transaction->getOrderTransaction()->getId(), $response->getMessage() ?? 'Payment was reported as failed.');
        }

        if (empty($response->getStatus())) {
            return;
        }

        $this->stateMachineRegistry->transition(
            new Transition(
                OrderTransactionDefinition::ENTITY_NAME,
                $transaction->getOrderTransaction()->getId(),
                $response->getStatus(),
                'stateId'
            ),
            $context
        );
    }

    protected function getAppPaymentMethod(OrderTransactionEntity $orderTransaction): AppPaymentMethodEntity
    {
        $paymentMethod = $orderTransaction->getPaymentMethod();
        if ($paymentMethod === null) {
            throw PaymentException::asyncProcessInterrupted($orderTransaction->getId(), 'Loaded data invalid');
        }

        $appPaymentMethod = $paymentMethod->getAppPaymentMethod();
        if ($appPaymentMethod === null) {
            throw PaymentException::asyncProcessInterrupted($orderTransaction->getId(), 'Loaded data invalid');
        }

        return $appPaymentMethod;
    }

    protected function buildRefundPayload(OrderTransactionCaptureRefundEntity $refund, OrderEntity $order): RefundPayload
    {
        return new RefundPayload(
            $refund,
            $order
        );
    }

    protected function buildValidatePayload(Cart $cart, RequestDataBag $requestDataBag, SalesChannelContext $context): ValidatePayload
    {
        return new ValidatePayload(
            $cart,
            $requestDataBag->all(),
            $context,
        );
    }

    protected function buildCapturePayload(PreparedPaymentTransactionStruct $transaction, Struct $preOrderPaymentStruct): CapturePayload
    {
        return new CapturePayload(
            $transaction->getOrderTransaction(),
            $transaction->getOrder(),
            $preOrderPaymentStruct,
            $transaction->getRecurring()
        );
    }

    protected function buildRecurringPayload(RecurringPaymentTransactionStruct $transaction): RecurringPayPayload
    {
        return new RecurringPayPayload(
            $transaction->getOrderTransaction(),
            $transaction->getOrder(),
            [],
            $transaction->getRecurring()
        );
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class PaymentException extends HttpException
{
    final public const PAYMENT_ASYNC_FINALIZE_INTERRUPTED = 'CHECKOUT__ASYNC_PAYMENT_FINALIZE_INTERRUPTED';
    final public const PAYMENT_ASYNC_PROCESS_INTERRUPTED = 'CHECKOUT__ASYNC_PAYMENT_PROCESS_INTERRUPTED';
    final public const PAYMENT_CAPTURE_PREPARED_ERROR = 'CHECKOUT__CAPTURE_PREPARED_PAYMENT_ERROR';
    final public const PAYMENT_CUSTOMER_CANCELED_EXTERNAL = 'CHECKOUT__CUSTOMER_CANCELED_EXTERNAL_PAYMENT';
    final public const PAYMENT_INVALID_ORDER_ID = 'CHECKOUT__INVALID_ORDER_ID';
    final public const PAYMENT__REFUND_INVALID_TRANSITION_ERROR = 'CHECKOUT__REFUND_INVALID_TRANSITION_ERROR';
    final public const PAYMENT_INVALID_TOKEN = 'CHECKOUT__INVALID_PAYMENT_TOKEN';
    final public const PAYMENT_INVALID_TRANSACTION_ID = 'CHECKOUT__INVALID_TRANSACTION_ID';

    final public const PAYMENT_PROCESS_ERROR = 'CHECKOUT__PAYMENT_ERROR';
    final public const PAYMENT_PLUGIN_PAYMENT_METHOD_DELETE_RESTRICTION = 'CHECKOUT__PLUGIN_PAYMENT_METHOD_DELETE_RESTRICTION';
    final public const PAYMENT_REFUND_PROCESS_INTERRUPTED = 'CHECKOUT__REFUND_PROCESS_INTERRUPTED';
    final public const PAYMENT_REFUND_PROCESS_ERROR = 'CHECKOUT__REFUND_PROCESS_ERROR';
    final public const PAYMENT_RECURRING_PROCESS_INTERRUPTED = 'CHECKOUT__RECURRING_PROCESS_INTERRUPTED';
    final public const PAYMENT_SYNC_PROCESS_INTERRUPTED = 'CHECKOUT__SYNC_PAYMENT_PROCESS_INTERRUPTED';
    final public const PAYMENT_TOKEN_EXPIRED = 'CHECKOUT__PAYMENT_TOKEN_EXPIRED';
    final public const PAYMENT_TOKEN_INVALIDATED = 'CHECKOUT__PAYMENT_TOKEN_INVALIDATED';
    final public const PAYMENT_UNKNOWN_PAYMENT_METHOD = 'CHECKOUT__UNKNOWN_PAYMENT_METHOD';
    final public const PAYMENT_REFUND_UNKNOWN_ERROR = 'CHECKOUT__REFUND_UNKNOWN_ERROR';
    final public const PAYMENT_REFUND_UNKNOWN_HANDLER_ERROR = 'CHECKOUT__REFUND_UNKNOWN_HANDLER_ERROR';
    final public const PAYMENT_VALIDATE_PREPARED_ERROR = 'CHECKOUT__VALIDATE_PREPARED_PAYMENT_ERROR';
    final public const PAYMENT_METHOD_DUPLICATE_TECHNICAL_NAME = 'CHECKOUT__DUPLICATE_PAYMENT_METHOD_TECHNICAL_NAME';

    public static function asyncFinalizeInterrupted(string $orderTransactionId, string $errorMessage, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_ASYNC_FINALIZE_INTERRUPTED,
            'The asynchronous payment finalize was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            [
                'errorMessage' => $errorMessage,
                'orderTransactionId' => $orderTransactionId,
            ],
            $e
        );
    }

    public static function asyncProcessInterrupted(string $orderTransactionId, string $errorMessage, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_ASYNC_PROCESS_INTERRUPTED,
            'The asynchronous payment process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            [
                'errorMessage' => $errorMessage,
                'orderTransactionId' => $orderTransactionId,
            ],
            $e
        );
    }

    public static function syncProcessInterrupted(string $orderTransactionId, string $errorMessage, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_SYNC_PROCESS_INTERRUPTED,
            'The synchronous payment process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            [
                'errorMessage' => $errorMessage,
                'orderTransactionId' => $orderTransactionId,
            ],
            $e
        );
    }

    public static function capturePreparedException(string $orderTransactionId, string $errorMessage, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_CAPTURE_PREPARED_ERROR,
            'The capture process of the prepared payment was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            [
                'errorMessage' => $errorMessage,
                'orderTransactionId' => $orderTransactionId,
            ],
            $e
        );
    }

    public static function customerCanceled(string $orderTransactionId, string $additionalInformation, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_CUSTOMER_CANCELED_EXTERNAL,
            'The customer canceled the external payment process. {{ additionalInformation }}',
            [
                'additionalInformation' => $additionalInformation,
                'orderTransactionId' => $orderTransactionId,
            ],
            $e
        );
    }

    public static function invalidOrder(string $orderId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::PAYMENT_INVALID_ORDER_ID,
            'The order with id {{ orderId }} is invalid or could not be found.',
            ['orderId' => $orderId],
            $e
        );
    }

    public static function refundInvalidTransition(string $refundId, string $stateName, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT__REFUND_INVALID_TRANSITION_ERROR,
            'The Refund process failed with following exception: Can not process refund with id {{ refundId }} as refund has state {{ stateName }}.',
            ['refundId' => $refundId, 'stateName' => $stateName],
            $e
        );
    }

    public static function invalidToken(string $token, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_INVALID_TOKEN,
            'The provided token {{ token }} is invalid and the payment could not be processed.',
            ['token' => $token],
            $e
        );
    }

    public static function invalidTransaction(string $transactionId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_INVALID_TRANSACTION_ID,
            'The transaction with id {{ transactionId }} is invalid or could not be found.',
            ['transactionId' => $transactionId],
            $e
        );
    }

    public static function pluginPaymentMethodDeleteRestriction(?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_PLUGIN_PAYMENT_METHOD_DELETE_RESTRICTION,
            'Plugin payment methods can not be deleted via API.',
            [],
            $e
        );
    }

    public static function refundInterrupted(string $refundId, string $errorMessage, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_REFUND_PROCESS_INTERRUPTED,
            'The refund process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            [
                'refundId' => $refundId,
                'errorMessage' => $errorMessage,
            ],
            $e
        );
    }

    public static function recurringInterrupted(string $transactionId, string $errorMessage, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_RECURRING_PROCESS_INTERRUPTED,
            'The recurring capture process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            [
                'orderTransactionId' => $transactionId,
                'errorMessage' => $errorMessage,
            ],
            $e
        );
    }

    public static function tokenExpired(string $token, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_TOKEN_EXPIRED,
            'The provided token {{ token }} is expired and the payment could not be processed.',
            [
                'token' => $token,
            ],
            $e
        );
    }

    public static function tokenInvalidated(string $token, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_TOKEN_INVALIDATED,
            'The provided token {{ token }} is invalidated and the payment could not be processed.',
            [
                'token' => $token,
            ],
            $e
        );
    }

    public static function unknownPaymentMethodById(string $paymentMethodId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_UNKNOWN_PAYMENT_METHOD,
            self::$couldNotFindMessage,
            ['entity' => 'payment method', 'field' => 'id', 'value' => $paymentMethodId],
            $e
        );
    }

    public static function unknownPaymentMethodByHandlerIdentifier(string $paymentMethodId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_UNKNOWN_PAYMENT_METHOD,
            self::$couldNotFindMessage,
            ['entity' => 'payment method', 'field' => 'handler identifier', 'value' => $paymentMethodId],
            $e
        );
    }

    public static function unknownRefund(string $refundId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_REFUND_UNKNOWN_ERROR,
            'The Refund process failed with following exception: Unknown refund with id {{ refundId }}.',
            ['refundId' => $refundId],
            $e
        );
    }

    public static function unknownRefundHandler(string $refundId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_REFUND_UNKNOWN_HANDLER_ERROR,
            'The Refund process failed with following exception: Unknown refund handler for refund id {{ refundId }}.',
            ['refundId' => $refundId],
            $e
        );
    }

    public static function validatePreparedPaymentInterrupted(string $errorMessage, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_VALIDATE_PREPARED_ERROR,
            'The validation process of the prepared payment was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            ['errorMessage' => $errorMessage],
            $e
        );
    }

    public static function duplicateTechnicalName(string $technicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PAYMENT_METHOD_DUPLICATE_TECHNICAL_NAME,
            'The technical name "{{ technicalName }}" is not unique.',
            ['technicalName' => $technicalName]
        );
    }

    public function getRefundId(): string
    {
        return $this->getParameter('refundId') ?? '';
    }

    public function getOrderTransactionId(): ?string
    {
        return $this->getParameter('orderTransactionId');
    }

    public function getOrderId(): ?string
    {
        return $this->getParameter('orderId');
    }
}

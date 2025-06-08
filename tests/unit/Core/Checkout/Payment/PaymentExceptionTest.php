<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Payment;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AbstractPaymentHandler;
use Shopware\Core\Checkout\Payment\Cart\PaymentHandler\PaymentHandlerType;
use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PaymentException::class)]
class PaymentExceptionTest extends TestCase
{
    public function testInvalidToken(): void
    {
        $exception = PaymentException::invalidToken('token');

        static::assertSame('The provided token token is invalid and the payment could not be processed.', $exception->getMessage());
        static::assertSame('CHECKOUT__INVALID_PAYMENT_TOKEN', $exception->getErrorCode());
    }

    public function testInvalidTransactionId(): void
    {
        $exception = PaymentException::invalidTransaction('transaction-id');

        static::assertSame('The transaction with id transaction-id is invalid or could not be found.', $exception->getMessage());
        static::assertSame('CHECKOUT__INVALID_TRANSACTION_ID', $exception->getErrorCode());
    }

    public function testInvalidOrder(): void
    {
        $exception = PaymentException::invalidOrder('order-id');

        static::assertSame('The order with id order-id is invalid or could not be found.', $exception->getMessage());
        static::assertSame('CHECKOUT__INVALID_ORDER_ID', $exception->getErrorCode());
    }

    public function testInvalidPaymentMethod(): void
    {
        $exception = PaymentException::unknownPaymentMethodById('payment-method-id');

        static::assertSame('Could not find payment method with id "payment-method-id"', $exception->getMessage());
        static::assertSame('CHECKOUT__UNKNOWN_PAYMENT_METHOD', $exception->getErrorCode());
    }

    public function testAsyncProcessInterrupted(): void
    {
        $exception = PaymentException::asyncProcessInterrupted('transaction-id', 'error');

        static::assertSame('The asynchronous payment process was interrupted due to the following error:
error', $exception->getMessage());
        static::assertSame('CHECKOUT__ASYNC_PAYMENT_PROCESS_INTERRUPTED', $exception->getErrorCode());
    }

    public function testAsyncFinalizeInterrupted(): void
    {
        $exception = PaymentException::asyncFinalizeInterrupted('transaction-id', 'error');

        static::assertSame('The asynchronous payment finalize was interrupted due to the following error:
error', $exception->getMessage());
        static::assertSame('CHECKOUT__ASYNC_PAYMENT_FINALIZE_INTERRUPTED', $exception->getErrorCode());
    }

    public function testSyncProcessInterrupted(): void
    {
        $exception = PaymentException::syncProcessInterrupted('transaction-id', 'error');

        static::assertSame('The synchronous payment process was interrupted due to the following error:
error', $exception->getMessage());
        static::assertSame('CHECKOUT__SYNC_PAYMENT_PROCESS_INTERRUPTED', $exception->getErrorCode());
    }

    public function testCapturePreparedException(): void
    {
        $exception = PaymentException::capturePreparedException('transaction-id', 'error');

        static::assertSame('The capture process of the prepared payment was interrupted due to the following error:
error', $exception->getMessage());
        static::assertSame('CHECKOUT__CAPTURE_PREPARED_PAYMENT_ERROR', $exception->getErrorCode());
    }

    public function testCustomerCanceled(): void
    {
        $exception = PaymentException::customerCanceled('transaction-id', 'error');

        static::assertSame('The customer canceled the external payment process. error', $exception->getMessage());
        static::assertSame('CHECKOUT__CUSTOMER_CANCELED_EXTERNAL_PAYMENT', $exception->getErrorCode());
    }

    public function testUnknownRefund(): void
    {
        $exception = PaymentException::unknownRefund('refund-id');

        static::assertSame('The Refund process failed with following exception: Unknown refund with id refund-id.', $exception->getMessage());
        static::assertSame('CHECKOUT__REFUND_UNKNOWN_ERROR', $exception->getErrorCode());
    }

    public function testRefundInvalidTransition(): void
    {
        $exception = PaymentException::refundInvalidTransition('refund-id', 'state');

        static::assertSame('The Refund process failed with following exception: Can not process refund with id refund-id as refund has state state.', $exception->getMessage());
        static::assertSame('CHECKOUT__REFUND_INVALID_TRANSITION_ERROR', $exception->getErrorCode());
    }

    public function testUnknownRefundHandler(): void
    {
        $exception = PaymentException::unknownRefundHandler('refund-id');

        static::assertSame('The Refund process failed with following exception: Unknown refund handler for refund id refund-id.', $exception->getMessage());
        static::assertSame('CHECKOUT__REFUND_UNKNOWN_HANDLER_ERROR', $exception->getErrorCode());
    }

    public function testValidatePreparedPaymentInterrupted(): void
    {
        $exception = PaymentException::validatePreparedPaymentInterrupted('error');

        static::assertSame('The validation process of the prepared payment was interrupted due to the following error:
error', $exception->getMessage());
        static::assertSame('CHECKOUT__VALIDATE_PREPARED_PAYMENT_ERROR', $exception->getErrorCode());
    }

    public function testCapturePreparedPaymentInterrupted(): void
    {
        $exception = PaymentException::capturePreparedException('transaction-id', 'error');

        static::assertSame('The capture process of the prepared payment was interrupted due to the following error:
error', $exception->getMessage());
        static::assertSame('CHECKOUT__CAPTURE_PREPARED_PAYMENT_ERROR', $exception->getErrorCode());
    }

    public function testPluginPaymentMethodDeleteRestriction(): void
    {
        $exception = PaymentException::pluginPaymentMethodDeleteRestriction();

        static::assertSame('Plugin payment methods can not be deleted via API.', $exception->getMessage());
        static::assertSame('CHECKOUT__PLUGIN_PAYMENT_METHOD_DELETE_RESTRICTION', $exception->getErrorCode());
    }

    public function testRefundInterrupted(): void
    {
        $exception = PaymentException::refundInterrupted('transaction-id', 'error');

        static::assertSame('The refund process was interrupted due to the following error:
error', $exception->getMessage());
        static::assertSame('CHECKOUT__REFUND_PROCESS_INTERRUPTED', $exception->getErrorCode());
    }

    public function testRecurringInterrupted(): void
    {
        $exception = PaymentException::recurringInterrupted('transaction-id', 'error');

        static::assertSame('The recurring capture process was interrupted due to the following error:
error', $exception->getMessage());
        static::assertSame('CHECKOUT__RECURRING_PROCESS_INTERRUPTED', $exception->getErrorCode());
    }

    public function testTokenExpired(): void
    {
        $exception = PaymentException::tokenExpired('token');

        static::assertSame('The provided token token is expired and the payment could not be processed.', $exception->getMessage());
        static::assertSame('CHECKOUT__PAYMENT_TOKEN_EXPIRED', $exception->getErrorCode());
    }

    public function testTokenInvalidated(): void
    {
        $exception = PaymentException::tokenInvalidated('token');

        static::assertSame('The provided token token is invalidated and the payment could not be processed.', $exception->getMessage());
        static::assertSame('CHECKOUT__PAYMENT_TOKEN_INVALIDATED', $exception->getErrorCode());
    }

    public function testUnknownPaymentMethod(): void
    {
        $exception = PaymentException::unknownPaymentMethodByHandlerIdentifier('payment-method-id');

        static::assertSame('Could not find payment method with handler identifier "payment-method-id"', $exception->getMessage());
        static::assertSame('CHECKOUT__UNKNOWN_PAYMENT_METHOD', $exception->getErrorCode());
    }

    public function testPaymentTypeNotSupported(): void
    {
        $exception = PaymentException::paymentTypeUnsupported('payment-method-id', PaymentHandlerType::RECURRING);

        static::assertSame('The payment method with id payment-method-id does not support the payment handler type RECURRING.', $exception->getMessage());
        static::assertSame('CHECKOUT__PAYMENT_TYPE_UNSUPPORTED', $exception->getErrorCode());
    }

    public function testPaymentHandlerTypeUnsupported(): void
    {
        $class = $this->createMock(AbstractPaymentHandler::class);
        $exception = PaymentException::paymentHandlerTypeUnsupported($class, PaymentHandlerType::RECURRING);

        static::assertSame('The payment handler ' . $class::class . ' does not support the payment handler type RECURRING.', $exception->getMessage());
        static::assertSame('CHECKOUT__PAYMENT_HANDLER_TYPE_UNSUPPORTED', $exception->getErrorCode());
    }

    public function testDuplicateTechnicalName(): void
    {
        $exception = PaymentException::duplicateTechnicalName('technical-name');

        static::assertSame('The technical name "technical-name" is not unique.', $exception->getMessage());
        static::assertSame('CHECKOUT__DUPLICATE_PAYMENT_METHOD_TECHNICAL_NAME', $exception->getErrorCode());
    }
}

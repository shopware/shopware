<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

class AsyncPaymentFinalizeException extends PaymentProcessException
{
    public function __construct(string $orderTransactionId, string $errorMessage)
    {
        parent::__construct(
            $orderTransactionId,
            'The asynchronous payment finalize was interrupted due to the following error:' . PHP_EOL . '{{ errorMessage }}',
            ['errorMessage' => $errorMessage]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__ASYNC_PAYMENT_FINALIZE_INTERRUPTED';
    }
}

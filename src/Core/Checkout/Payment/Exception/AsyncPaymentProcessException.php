<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

class AsyncPaymentProcessException extends PaymentProcessException
{
    public function __construct(string $orderTransactionId, string $errorMessage, ?\Throwable $previous = null)
    {
        parent::__construct(
            $orderTransactionId,
            'The asynchronous payment process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            ['errorMessage' => $errorMessage],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__ASYNC_PAYMENT_PROCESS_INTERRUPTED';
    }
}

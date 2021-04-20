<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

class SyncPaymentProcessException extends PaymentProcessException
{
    public function __construct(string $orderTransactionId, string $errorMessage, ?\Throwable $previous = null)
    {
        parent::__construct(
            $orderTransactionId,
            'The synchronous payment process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            ['errorMessage' => $errorMessage],
            $previous
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__SYNC_PAYMENT_PROCESS_INTERRUPTED';
    }
}

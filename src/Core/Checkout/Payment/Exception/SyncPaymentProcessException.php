<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

class SyncPaymentProcessException extends PaymentProcessException
{
    public function __construct(string $orderTransactionId, string $errorMessage)
    {
        parent::__construct(
            $orderTransactionId,
            'The synchronous payment process was interrupted due to the following error:' . PHP_EOL . '{{ errorMessage }}',
            ['errorMessage' => $errorMessage]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__SYNC_PAYMENT_PROCESS_INTERRUPTED';
    }
}

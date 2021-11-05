<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

class ValidatePreparedPaymentException extends PaymentProcessException
{
    public function __construct(string $orderTransactionId, string $errorMessage)
    {
        parent::__construct(
            $orderTransactionId,
            'The validation process of the prepared payment was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            ['errorMessage' => $errorMessage]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__VALIDATE_PREPARED_PAYMENT_ERROR';
    }
}

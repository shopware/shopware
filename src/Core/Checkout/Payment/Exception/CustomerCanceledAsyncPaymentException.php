<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

class CustomerCanceledAsyncPaymentException extends PaymentProcessException
{
    public function __construct(string $orderTransactionId, string $additionalInformation)
    {
        parent::__construct(
            $orderTransactionId,
            'The customer canceled the external payment process. Additional information:' . PHP_EOL . '{{ additionalInformation }}',
            ['additionalInformation' => $additionalInformation]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_CANCELED_EXTERNAL_PAYMENT';
    }
}

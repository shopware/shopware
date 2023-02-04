<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
class CustomerCanceledAsyncPaymentException extends PaymentProcessException
{
    public function __construct(
        string $orderTransactionId,
        string $additionalInformation = '',
        ?\Throwable $e = null
    ) {
        parent::__construct(
            $orderTransactionId,
            'The customer canceled the external payment process. {{ additionalInformation }}',
            ['additionalInformation' => $additionalInformation],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__CUSTOMER_CANCELED_EXTERNAL_PAYMENT';
    }
}

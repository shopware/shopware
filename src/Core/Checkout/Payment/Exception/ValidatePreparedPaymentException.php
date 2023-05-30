<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('checkout')]
class ValidatePreparedPaymentException extends ShopwareHttpException
{
    public function __construct(
        string $errorMessage,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'The validation process of the prepared payment was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            ['errorMessage' => $errorMessage],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__VALIDATE_PREPARED_PAYMENT_ERROR';
    }
}

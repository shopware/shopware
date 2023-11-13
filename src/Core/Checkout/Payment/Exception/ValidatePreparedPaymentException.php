<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
/**
 * @decrecated tag:v6.6.0 - use PaymentException::validatePreparedPaymentInterrupted instead
 */
class ValidatePreparedPaymentException extends PaymentException
{
    public function __construct(
        string $errorMessage,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            'CHECKOUT__VALIDATE_PREPARED_PAYMENT_ERROR',
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

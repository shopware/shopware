<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Payment;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class AppPaymentException extends HttpException
{
    final public const APP_PAYMENT_INVALID_TRANSACTION_ID = 'APP_PAYMENT__INVALID_TRANSACTION_ID';

    final public const APP_PAYMENT_INTERRUPTED = 'APP_PAYMENT__INTERRUPTED';

    public static function invalidTransaction(string $transactionId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_PAYMENT_INVALID_TRANSACTION_ID,
            'The transaction with id {{ transactionId }} is invalid or could not be found.',
            ['transactionId' => $transactionId],
            $e
        );
    }

    public static function interrupted(string $errorMessage, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_PAYMENT_INTERRUPTED,
            'The app payment process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            [
                'errorMessage' => $errorMessage,
            ],
            $e
        );
    }
}

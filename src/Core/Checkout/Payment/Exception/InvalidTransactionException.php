<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class InvalidTransactionException extends ShopwareHttpException
{
    public function __construct(
        string $transactionId,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            'The transaction with id {{ transactionId }} is invalid or could not be found.',
            ['transactionId' => $transactionId],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__INVALID_TRANSACTION_ID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}

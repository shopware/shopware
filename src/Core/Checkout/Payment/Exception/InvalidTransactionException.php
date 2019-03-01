<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidTransactionException extends ShopwareHttpException
{
    protected $code = 'INVALID-TRANSACTION-ID';

    public function __construct(string $transactionId, $code = 0, ?\Throwable $previous = null)
    {
        $message = sprintf('The transaction with id %s is invalid or could not be found.', $transactionId);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}

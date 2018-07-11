<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidOrderException extends ShopwareHttpException
{
    protected $code = 'INVALID-ORDER-ID';

    public function __construct(string $orderId, $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The order with id %s is invalid or could not be found.', $orderId);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class InvalidTokenException extends ShopwareHttpException
{
    protected $code = 'INVALID-PAYMENT-TOKEN';

    public function __construct(string $token, $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('The provided token %s is invalid and the payment could not be processed.', $token);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}

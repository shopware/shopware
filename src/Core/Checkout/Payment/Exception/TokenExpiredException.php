<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class TokenExpiredException extends ShopwareHttpException
{
    protected $code = 'PAYMENT-TOKEN-EXPIRED';

    public function __construct(string $token, $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('The provided token %s is expired and the payment could not be processed.', $token);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_GONE;
    }
}

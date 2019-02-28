<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class UnknownPaymentMethodException extends ShopwareHttpException
{
    protected $code = 'UNKNOWN-PAYMENT-METHOD';

    public function __construct(string $token, $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('The payment method %s could not be found.', $token);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}

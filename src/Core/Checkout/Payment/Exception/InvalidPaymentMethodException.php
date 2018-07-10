<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidPaymentMethodException extends ShopwareHttpException
{
    protected $code = 'INVALID-PAYMENT-METHOD';

    public function __construct(string $token, $code = 0, Throwable $previous = null)
    {
        $message = sprintf('The payment method %s has an incomplete configuration and is therefore invalid.', $token);

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_IMPLEMENTED;
    }
}

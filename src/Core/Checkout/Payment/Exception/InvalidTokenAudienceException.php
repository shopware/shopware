<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InvalidTokenAudienceException extends ShopwareHttpException
{
    protected $code = 'INVALID-PAYMENT-TOKEN-ISSUER';

    public function __construct(string $expected, string $actual, string $token, $code = 0, Throwable $previous = null)
    {
        $message = sprintf(
            'The token audience "%s" does not match with "%s" and the payment could not be processed. Token: "%s"',
            $expected,
            $actual,
            $token
        );

        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}

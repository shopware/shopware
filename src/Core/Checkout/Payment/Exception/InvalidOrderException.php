<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
/**
 * @decrecated tag:v6.6.0 - use PaymentException::invalidOrder instead
 */
class InvalidOrderException extends PaymentException
{
    public function __construct(
        string $orderId,
        ?\Throwable $e = null
    ) {
        parent::__construct(
            Response::HTTP_NOT_FOUND,
            'CHECKOUT__INVALID_ORDER_ID',
            'The order with id {{ orderId }} is invalid or could not be found.',
            ['orderId' => $orderId],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__INVALID_ORDER_ID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}

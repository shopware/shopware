<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
abstract class PaymentProcessException extends ShopwareHttpException
{
    public function __construct(
        private readonly string $orderTransactionId,
        string $message,
        array $parameters = [],
        ?\Throwable $e = null
    ) {
        parent::__construct($message, $parameters, $e);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getOrderTransactionId(): string
    {
        return $this->orderTransactionId;
    }
}

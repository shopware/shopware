<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
abstract class RefundProcessException extends ShopwareHttpException
{
    public function __construct(
        private readonly string $refundId,
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

    public function getRefundId(): string
    {
        return $this->refundId;
    }
}

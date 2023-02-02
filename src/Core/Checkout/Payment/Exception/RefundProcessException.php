<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

abstract class RefundProcessException extends ShopwareHttpException
{
    private string $refundId;

    public function __construct(string $refundId, string $message, array $parameters = [], ?\Throwable $e = null)
    {
        $this->refundId = $refundId;

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

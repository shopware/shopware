<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Checkout\Payment\PaymentException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
/**
 * @decrecated tag:v6.6.0 - use PaymentException instead
 */
abstract class RefundProcessException extends PaymentException
{
    public function __construct(
        private readonly string $refundId,
        string $message,
        array $parameters = [],
        ?\Throwable $e = null
    ) {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            PaymentException::PAYMENT_REFUND_PROCESS_ERROR,
            $message,
            $parameters,
            $e
        );
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

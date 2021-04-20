<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Payment\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

abstract class PaymentProcessException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $orderTransactionId;

    public function __construct(string $orderTransactionId, string $message, array $parameters = [], ?\Throwable $previous = null)
    {
        $this->orderTransactionId = $orderTransactionId;

        parent::__construct($message, $parameters, $previous);
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

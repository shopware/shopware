<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Refund\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class PaymentRefundProcessException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $orderRefundId;

    public function __construct(string $orderRefundId, string $message, array $parameters = [])
    {
        $this->orderRefundId = $orderRefundId;

        parent::__construct($message, $parameters);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getOrderRefundId(): string
    {
        return $this->orderRefundId;
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__PAYMENT_REFUND_PROCESS_INTERRUPTED';
    }
}

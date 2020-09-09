<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderInconsistentException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $orderId;

    public function __construct(string $orderId, string $reason)
    {
        parent::__construct(
            'Inconsistent order with id "{{ orderId }}". Reason: {{ reason }}',
            ['orderId' => $orderId, 'reason' => $reason]
        );

        $this->orderId = $orderId;
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__ORDER_INCONSISTENT';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }
}

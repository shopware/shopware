<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderPaidException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $orderId;

    public function __construct(string $orderId)
    {
        parent::__construct(
            'Order with id "{{ orderId }}" was already paid and cannot be edited afterwards.',
            ['orderId' => $orderId]
        );

        $this->orderId = $orderId;
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__ORDER_PAID';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }
}

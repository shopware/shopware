<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderDeliveryNotFoundException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $orderDeliveryId;

    public function __construct(string $orderDeliveryId)
    {
        parent::__construct(
            'Order delivery with id "{{ orderDeliveryId }}" not found.',
            ['orderDeliveryId' => $orderDeliveryId]
        );

        $this->orderDeliveryId = $orderDeliveryId;
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__ORDER_DELIVERY_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getOrderDeliveryId(): string
    {
        return $this->orderDeliveryId;
    }
}

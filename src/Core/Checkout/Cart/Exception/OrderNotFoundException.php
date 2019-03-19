<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderNotFoundException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $orderId;

    public function __construct(string $orderId)
    {
        parent::__construct(
            'Order with id "{{ orderId }}" not found.',
            ['orderId' => $orderId]
        );

        $this->orderId = $orderId;
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__ORDER_NOT_FOUND';
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

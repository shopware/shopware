<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderTransactionNotFoundException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $orderTransactionId;

    public function __construct(string $orderTransactionId)
    {
        parent::__construct(
            'Order transaction with id "{{ orderTransactionId }}" not found.',
            ['orderTransactionId' => $orderTransactionId]
        );

        $this->orderTransactionId = $orderTransactionId;
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__ORDER_TRANSACTION_NOT_FOUND';
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

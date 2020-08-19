<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderTransactionCaptureNotFoundException extends ShopwareHttpException
{
    /**
     * @var string
     */
    private $orderTransactionCaptureId;

    public function __construct(string $orderTransactionCaptureId)
    {
        parent::__construct(
            'Order transaction capture with id "{{ orderTransactionCaptureId }}" not found.',
            ['orderTransactionCaptureId' => $orderTransactionCaptureId]
        );

        $this->orderTransactionCaptureId = $orderTransactionCaptureId;
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__ORDER_TRANSACTION_CAPTURE_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }

    public function getOrderTransactionCaptureId(): string
    {
        return $this->orderTransactionCaptureId;
    }
}

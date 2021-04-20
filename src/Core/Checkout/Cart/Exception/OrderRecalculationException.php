<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderRecalculationException extends ShopwareHttpException
{
    /**
     * @var string
     */
    protected $orderId;

    public function __construct(string $orderId, string $details, ?\Throwable $previous = null)
    {
        parent::__construct(
            'Order with id "{{ orderId }}" could not be recalculated. {{ details }}',
            ['orderId' => $orderId, 'details' => $details],
            $previous
        );

        $this->orderId = $orderId;
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__ORDER_RECALCULATION_FAILED';
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

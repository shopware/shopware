<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OrderRecalculationException extends ShopwareHttpException
{
    protected $code = 'ORDER-RECALCULATION-FAILED';

    /**
     * @var string
     */
    protected $orderId;

    public function __construct(string $orderId, string $details, $code = 0, Throwable $previous = null)
    {
        $message = sprintf('Order with id "%s" could not be recalculated. %s', $orderId, $details);
        parent::__construct($message, $code, $previous);
        $this->orderId = $orderId;
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

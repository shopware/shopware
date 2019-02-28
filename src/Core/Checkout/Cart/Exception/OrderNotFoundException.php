<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class OrderNotFoundException extends ShopwareHttpException
{
    protected $code = 'ORDER-NOT-FOUND';

    /**
     * @var string
     */
    private $orderId;

    public function __construct(string $orderId, $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('Order with id "%s" not found', $orderId);
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

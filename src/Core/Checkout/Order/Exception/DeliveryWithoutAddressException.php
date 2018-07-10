<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DeliveryWithoutAddressException extends ShopwareHttpException
{
    protected $code = 'DELIVERY-WITHOUT-ADDRESS';

    public function __construct($code = 0, Throwable $previous = null)
    {
        $message = 'Delivery contains no shipping address';
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

class ShippingMethodNotFoundException extends ShopwareHttpException
{
    public $code = 'SHIPPING-METHOD-NOT-FOUND';

    public function __construct(string $id, int $code = 0, \Throwable $previous = null)
    {
        $message = sprintf('Shipping method with id %s not found', $id);
        parent::__construct($message, $code, $previous);
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}

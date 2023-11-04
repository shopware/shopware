<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class ShippingMethodNotFoundException extends ShopwareHttpException
{
    public function __construct(string $id)
    {
        parent::__construct(
            'Shipping method with id "{{ shippingMethodId }}" not found.',
            ['shippingMethodId' => $id]
        );
    }

    public function getErrorCode(): string
    {
        return 'CHECKOUT__SHIPPING_METHOD_NOT_FOUND';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping\Exception;

use Shopware\Core\Checkout\Shipping\ShippingException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @decrecated tag:v6.6.0 - use ShippingException::shippingMethodNotFound instead
 */
#[Package('checkout')]
class ShippingMethodNotFoundException extends ShippingException
{
    public function __construct(string $id, ?\Throwable $e = null)
    {
        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            ShippingException::SHIPPING_METHOD_NOT_FOUND,
            'Shipping method with id "{{ shippingMethodId }}" not found.',
            ['shippingMethodId' => $id],
            $e
        );
    }

    public function getErrorCode(): string
    {
        return ShippingException::SHIPPING_METHOD_NOT_FOUND;
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}

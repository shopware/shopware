<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Checkout\Shipping\Exception\ShippingMethodNotFoundException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class ShippingException extends HttpException
{
    final public const SHIPPING_METHOD_NOT_FOUND = 'CHECKOUT__SHIPPING_METHOD_NOT_FOUND';

    final public const SHIPPING_METHOD_DUPLICATE_PRICE = 'CHECKOUT__DUPLICATE_SHIPPING_METHOD_PRICE';

    public static function shippingMethodNotFound(string $id, ?\Throwable $e = null): self
    {
        if (!Feature::isActive('v6.6.0.0')) {
            return new ShippingMethodNotFoundException($id, $e);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SHIPPING_METHOD_NOT_FOUND,
            'Shipping method with id "{{ shippingMethodId }}" not found.',
            ['shippingMethodId' => $id],
            $e
        );
    }

    public static function duplicateShippingMethodPrice(?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SHIPPING_METHOD_DUPLICATE_PRICE,
            'Shipping method price quantity already exists.',
            [],
            $e
        );
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('checkout')]
class ShippingException extends HttpException
{
    final public const SHIPPING_METHOD_NOT_FOUND = 'CHECKOUT__SHIPPING_METHOD_NOT_FOUND';

    final public const SHIPPING_METHOD_DUPLICATE_PRICE = 'CHECKOUT__DUPLICATE_SHIPPING_METHOD_PRICE';

    final public const SHIPPING_METHOD_DUPLICATE_TECHNICAL_NAME = 'CHECKOUT__DUPLICATE_SHIPPING_METHOD_TECHNICAL_NAME';

    public static function shippingMethodNotFound(string $id, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SHIPPING_METHOD_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'shipping method', 'field' => 'id', 'value' => $id],
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

    public static function duplicateTechnicalName(string $technicalName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SHIPPING_METHOD_DUPLICATE_TECHNICAL_NAME,
            'The technical name "{{ technicalName }}" is not unique.',
            ['technicalName' => $technicalName]
        );
    }
}

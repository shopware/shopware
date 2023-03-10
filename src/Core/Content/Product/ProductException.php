<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class ProductException extends HttpException
{
    public const PRODUCT_MISSING_PRICING_PROXY_PROPERTY_CODE = 'PRODUCT_MISSING_PRICING_PROXY_PROPERTY';
    public const PRODUCT__PROXY_MANIPULATION_NOT_ALLOWED_CODE = 'PRODUCT_PROXY_MANIPULATION_NOT_ALLOWED';

    public static function missingPricingProxyProperty(string $id, string $property): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PRODUCT_MISSING_PRICING_PROXY_PROPERTY_CODE,
            'Required pricing proxy field {{ property }} missing for product with id {{ id }}',
            ['id' => $id, 'property' => $property]
        );
    }

    public static function proxyManipulationNotAllowed(mixed $property): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PRODUCT__PROXY_MANIPULATION_NOT_ALLOWED_CODE,
            'Manipulation of pricing proxy field {{ property }} is not allowed',
            ['property' => (string) $property]
        );
    }
}

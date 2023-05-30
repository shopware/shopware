<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('inventory')]
class ProductException extends HttpException
{
    public const PRODUCT_INVALID_CHEAPEST_PRICE_FACADE = 'PRODUCT_INVALID_CHEAPEST_PRICE_FACADE';
    public const PRODUCT_PROXY_MANIPULATION_NOT_ALLOWED_CODE = 'PRODUCT_PROXY_MANIPULATION_NOT_ALLOWED';
    public const PRODUCT_INVALID_PRICE_DEFINITION_CODE = 'PRODUCT_INVALID_PRICE_DEFINITION';
    public const CATEGORY_NOT_FOUND = 'PRODUCT__CATEGORY_NOT_FOUND';

    public static function invalidCheapestPriceFacade(string $id): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PRODUCT_INVALID_CHEAPEST_PRICE_FACADE,
            'Cheapest price facade for product {{ id }} is invalid',
            ['id' => $id]
        );
    }

    public static function invalidPriceDefinition(): self
    {
        return new self(
            Response::HTTP_CONFLICT,
            self::PRODUCT_INVALID_PRICE_DEFINITION_CODE,
            'Provided price definition is invalid.'
        );
    }

    public static function proxyManipulationNotAllowed(mixed $property): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PRODUCT_PROXY_MANIPULATION_NOT_ALLOWED_CODE,
            'Manipulation of pricing proxy field {{ property }} is not allowed',
            ['property' => (string) $property]
        );
    }

    public static function categoryNotFound(string $categoryId): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::CATEGORY_NOT_FOUND,
            'Category "{{ categoryId }}" not found.',
            ['categoryId' => $categoryId]
        );
    }
}

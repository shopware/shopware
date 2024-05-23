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
    public const SORTING_NOT_FOUND = 'PRODUCT_SORTING_NOT_FOUND';
    public const PRODUCT_CONFIGURATION_OPTION_ALREADY_EXISTS = 'PRODUCT_CONFIGURATION_OPTION_EXISTS_ALREADY';
    public const PRODUCT_INVALID_PRICE_IMPORT_PAYLOAD = 'PRODUCT_INVALID_PRICE_IMPORT_PAYLOAD';

    public static function invalidPriceImportPayload(array $errors): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PRODUCT_INVALID_PRICE_IMPORT_PAYLOAD,
            'Invalid price import payload',
            ['errors' => $errors]
        );
    }

    public static function invalidCheapestPriceFacade(string $id): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::PRODUCT_INVALID_CHEAPEST_PRICE_FACADE,
            'Cheapest price facade for product {{ id }} is invalid',
            ['id' => $id]
        );
    }

    public static function sortingNotFoundException(string $key): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::SORTING_NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'sorting', 'field' => 'key', 'value' => $key]
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
            self::$couldNotFindMessage,
            ['entity' => 'category', 'field' => 'id', 'value' => $categoryId]
        );
    }

    public static function configurationOptionAlreadyExists(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::PRODUCT_CONFIGURATION_OPTION_ALREADY_EXISTS,
            'Configuration option already exists'
        );
    }
}

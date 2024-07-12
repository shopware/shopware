<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(ProductException::class)]
class ProductExceptionTest extends TestCase
{
    public function testInvalidCheapestPriceFacade(): void
    {
        $productId = 'product-id-1';

        $exception = ProductException::invalidCheapestPriceFacade($productId);

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals(ProductException::PRODUCT_INVALID_CHEAPEST_PRICE_FACADE, $exception->getErrorCode());
        static::assertEquals('Cheapest price facade for product product-id-1 is invalid', $exception->getMessage());
        static::assertEquals(['id' => $productId], $exception->getParameters());
    }

    public function testSortingNotFound(): void
    {
        $key = 'value';

        $exception = ProductException::sortingNotFoundException($key);

        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertEquals(ProductException::SORTING_NOT_FOUND, $exception->getErrorCode());
        static::assertEquals('Could not find sorting with key "value"', $exception->getMessage());
        static::assertEquals(['entity' => 'sorting', 'field' => 'key', 'value' => $key], $exception->getParameters());
    }

    public function testInvalidPriceDefinition(): void
    {
        $exception = ProductException::invalidPriceDefinition();

        static::assertEquals(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertEquals(ProductException::PRODUCT_INVALID_PRICE_DEFINITION_CODE, $exception->getErrorCode());
        static::assertEquals('Provided price definition is invalid.', $exception->getMessage());
    }

    public function testProxyManipulationNotAllowed(): void
    {
        /** @var mixed $property */
        $property = 'property';

        $exception = ProductException::proxyManipulationNotAllowed($property);

        static::assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertEquals(ProductException::PRODUCT_PROXY_MANIPULATION_NOT_ALLOWED_CODE, $exception->getErrorCode());
        static::assertEquals('Manipulation of pricing proxy field property is not allowed', $exception->getMessage());
        static::assertEquals(['property' => $property], $exception->getParameters());
    }

    public function testCategoryNotFound(): void
    {
        $categoryId = 'category-id';

        $exception = ProductException::categoryNotFound($categoryId);

        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertEquals(ProductException::CATEGORY_NOT_FOUND, $exception->getErrorCode());
        static::assertEquals('Could not find category with id "category-id"', $exception->getMessage());
        static::assertEquals(['entity' => 'category', 'field' => 'id', 'value' => $categoryId], $exception->getParameters());
    }

    public function testConfigurationOptionAlreadyExists(): void
    {
        $exception = ProductException::configurationOptionAlreadyExists();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(ProductException::PRODUCT_CONFIGURATION_OPTION_ALREADY_EXISTS, $exception->getErrorCode());
        static::assertEquals('Configuration option already exists', $exception->getMessage());
    }

    public function testInvalidOptionsParameter(): void
    {
        $exception = ProductException::invalidOptionsParameter();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(ProductException::PRODUCT_INVALID_OPTIONS_PARAMETER, $exception->getErrorCode());
        static::assertEquals('The parameter options is invalid.', $exception->getMessage());
    }
}

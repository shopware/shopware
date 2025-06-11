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

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(ProductException::PRODUCT_INVALID_CHEAPEST_PRICE_FACADE, $exception->getErrorCode());
        static::assertSame('Cheapest price facade for product product-id-1 is invalid', $exception->getMessage());
        static::assertSame(['id' => $productId], $exception->getParameters());
    }

    public function testSortingNotFound(): void
    {
        $key = 'value';

        $exception = ProductException::sortingNotFoundException($key);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(ProductException::SORTING_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find sorting with key "value"', $exception->getMessage());
        static::assertSame(['entity' => 'sorting', 'field' => 'key', 'value' => $key], $exception->getParameters());
    }

    public function testInvalidPriceDefinition(): void
    {
        $exception = ProductException::invalidPriceDefinition();

        static::assertSame(Response::HTTP_CONFLICT, $exception->getStatusCode());
        static::assertSame(ProductException::PRODUCT_INVALID_PRICE_DEFINITION_CODE, $exception->getErrorCode());
        static::assertSame('Provided price definition is invalid.', $exception->getMessage());
    }

    public function testProxyManipulationNotAllowed(): void
    {
        /** @var mixed $property */
        $property = 'property';

        $exception = ProductException::proxyManipulationNotAllowed($property);

        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $exception->getStatusCode());
        static::assertSame(ProductException::PRODUCT_PROXY_MANIPULATION_NOT_ALLOWED_CODE, $exception->getErrorCode());
        static::assertSame('Manipulation of pricing proxy field property is not allowed', $exception->getMessage());
        static::assertSame(['property' => $property], $exception->getParameters());
    }

    public function testCategoryNotFound(): void
    {
        $categoryId = 'category-id';

        $exception = ProductException::categoryNotFound($categoryId);

        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame(ProductException::CATEGORY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Could not find category with id "category-id"', $exception->getMessage());
        static::assertSame(['entity' => 'category', 'field' => 'id', 'value' => $categoryId], $exception->getParameters());
    }

    public function testConfigurationOptionAlreadyExists(): void
    {
        $exception = ProductException::configurationOptionAlreadyExists();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ProductException::PRODUCT_CONFIGURATION_OPTION_ALREADY_EXISTS, $exception->getErrorCode());
        static::assertSame('Configuration option already exists', $exception->getMessage());
    }

    public function testInvalidOptionsParameter(): void
    {
        $exception = ProductException::invalidOptionsParameter();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(ProductException::PRODUCT_INVALID_OPTIONS_PARAMETER, $exception->getErrorCode());
        static::assertSame('The parameter options is invalid.', $exception->getMessage());
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Breadcrumb;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\BreadcrumbException;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(BreadcrumbException::class)]
class BreadcrumbExceptionTest extends TestCase
{
    public function testCategoryNotFoundForProductReturnsCorrectException(): void
    {
        $exception = BreadcrumbException::categoryNotFoundForProduct('invalidProductId');

        static::assertSame(Response::HTTP_NO_CONTENT, $exception->getStatusCode());
        static::assertSame('BREADCRUMB_CATEGORY_NOT_FOUND', $exception->getErrorCode());
        static::assertSame('The main category for product invalidProductId is not found', $exception->getMessage());
    }

    public function testCategoryNotFoundReturnsCorrectException(): void
    {
        $exception = BreadcrumbException::categoryNotFound('invalidId');

        static::assertInstanceOf(CategoryNotFoundException::class, $exception);
        static::assertSame('CONTENT__CATEGORY_NOT_FOUND', $exception->getErrorCode());
    }

    public function testProductNotFoundReturnsCorrectException(): void
    {
        $exception = BreadcrumbException::productNotFound('invalidId');

        static::assertInstanceOf(ProductNotFoundException::class, $exception);
        static::assertSame('CONTENT__PRODUCT_NOT_FOUND', $exception->getErrorCode());
    }
}

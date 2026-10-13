<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Breadcrumb\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\BreadcrumbException;
use Shopware\Core\Content\Breadcrumb\Exception\CategoryNotFoundForProductException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CategoryNotFoundForProductException::class)]
class CategoryNotFoundForProductExceptionTest extends TestCase
{
    public function testDescribesTheProductWithoutMainCategory(): void
    {
        $exception = new CategoryNotFoundForProductException('product-1');

        static::assertSame(Response::HTTP_NO_CONTENT, $exception->getStatusCode());
        static::assertSame(BreadcrumbException::BREADCRUMB_CATEGORY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('The main category for product product-1 is not found', $exception->getMessage());
    }

    /**
     * Callers that catch BreadcrumbException keep working, while the analytics route catches this one.
     */
    public function testIsTheExceptionTheBreadcrumbFactoryReturns(): void
    {
        static::assertInstanceOf(
            CategoryNotFoundForProductException::class,
            BreadcrumbException::categoryNotFoundForProduct('product-1')
        );
    }
}

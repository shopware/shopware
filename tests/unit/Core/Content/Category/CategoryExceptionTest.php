<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Category;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryException;
use Shopware\Core\Content\Category\Exception\CategoryNotFoundException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(CategoryException::class)]
class CategoryExceptionTest extends TestCase
{
    public function testCategoryNotFound(): void
    {
        $categoryId = 'category-id';

        $exception = CategoryException::categoryNotFound($categoryId);

        static::assertInstanceOf(CategoryNotFoundException::class, $exception);
        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
    }

    public function testServiceCategoryNotFoundForSalesChannel(): void
    {
        $salesChannelName = 'sales-channel-name';

        $exception = CategoryException::serviceCategoryNotFoundForSalesChannel($salesChannelName);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(CategoryException::SERVICE_CATEGORY_NOT_FOUND, $exception->getErrorCode());
        static::assertEquals('Service category, for sales channel sales-channel-name, is not set', $exception->getMessage());
        static::assertEquals(['salesChannelName' => $salesChannelName], $exception->getParameters());
    }

    public function testFooterCategoryNotFoundForSalesChannel(): void
    {
        $salesChannelName = 'sales-channel-name';

        $exception = CategoryException::footerCategoryNotFoundForSalesChannel($salesChannelName);

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(CategoryException::FOOTER_CATEGORY_NOT_FOUND, $exception->getErrorCode());
        static::assertEquals('Footer category, for sales channel sales-channel-name, is not set', $exception->getMessage());
        static::assertEquals(['salesChannelName' => $salesChannelName], $exception->getParameters());
    }

    public function testAfterCategoryNotFound(): void
    {
        $exception = CategoryException::afterCategoryNotFound();

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals(CategoryException::AFTER_CATEGORY_NOT_FOUND, $exception->getErrorCode());
        static::assertEquals('Category to insert after not found.', $exception->getMessage());
    }

    public function testInvalidCategoryId(): void
    {
        $exception = CategoryException::invalidCategoryId('invalid-category-id');

        static::assertEquals(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertEquals('CONTENT__CATEGORY_INVALID_ID', $exception->getErrorCode());
        static::assertEquals('Category "invalid-category-id" is invalid.', $exception->getMessage());
    }
}

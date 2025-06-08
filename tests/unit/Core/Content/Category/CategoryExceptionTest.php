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
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
    }

    public function testServiceCategoryNotFoundForSalesChannel(): void
    {
        $salesChannelName = 'sales-channel-name';

        $exception = CategoryException::serviceCategoryNotFoundForSalesChannel($salesChannelName);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CategoryException::SERVICE_CATEGORY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Service category, for sales channel sales-channel-name, is not set', $exception->getMessage());
        static::assertSame(['salesChannelName' => $salesChannelName], $exception->getParameters());
    }

    public function testFooterCategoryNotFoundForSalesChannel(): void
    {
        $salesChannelName = 'sales-channel-name';

        $exception = CategoryException::footerCategoryNotFoundForSalesChannel($salesChannelName);

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CategoryException::FOOTER_CATEGORY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Footer category, for sales channel sales-channel-name, is not set', $exception->getMessage());
        static::assertSame(['salesChannelName' => $salesChannelName], $exception->getParameters());
    }

    public function testAfterCategoryNotFound(): void
    {
        $exception = CategoryException::afterCategoryNotFound();

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(CategoryException::AFTER_CATEGORY_NOT_FOUND, $exception->getErrorCode());
        static::assertSame('Category to insert after not found.', $exception->getMessage());
    }
}

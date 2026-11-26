<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Exception\ProductListingPageOutOfRangeException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductListingPageOutOfRangeException::class)]
class ProductListingPageOutOfRangeExceptionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $exception = new ProductListingPageOutOfRangeException(99, 3);

        static::assertSame('PRODUCT__LISTING_PAGE_OUT_OF_RANGE', $exception->getErrorCode());
        static::assertSame(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertSame('Requested listing page 99 is out of range (last page: 3).', $exception->getMessage());
        static::assertSame(['requestedPage' => 99, 'lastPage' => 3], $exception->getParameters());
    }
}

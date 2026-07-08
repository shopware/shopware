<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\UnmappedFieldException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(UnmappedFieldException::class)]
class UnmappedFieldExceptionTest extends TestCase
{
    public function testException(): void
    {
        $exception = new UnmappedFieldException('product.categoriesRo.id', new ProductDefinition());

        static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
        static::assertSame(DataAbstractionLayerException::DBAL_UNMAPPED_FIELD, $exception->getErrorCode());
        // the last segment of the dotted field path is used as the field name
        static::assertSame('Field "id" in entity "product" was not found.', $exception->getMessage());
    }
}

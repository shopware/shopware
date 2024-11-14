<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Exception;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Exception\VariantNotFoundException;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[CoversClass(VariantNotFoundException::class)]
class VariantNotFoundExceptionTest extends TestCase
{
    public function testInstantiate(): void
    {
        $ids = new IdsCollection();

        $options = [
            $ids->get('groupId1') => $ids->get('optionId1'),
            $ids->get('groupId1') => $ids->get('optionId2'),
            $ids->get('groupId2') => $ids->get('optionId3'),
        ];

        $exception = new VariantNotFoundException($ids->get('productId'), $options);

        static::assertEquals('CONTENT__PRODUCT_VARIANT_NOT_FOUND', $exception->getErrorCode());
        static::assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatusCode());
        static::assertEquals(
            'Variant for productId ' . $ids->get('productId') . ' with options ' . \json_encode($options, \JSON_THROW_ON_ERROR) . ' not found.',
            $exception->getMessage()
        );
    }
}

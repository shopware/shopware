<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Content\Product\Cart\ProductOutOfStockError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductOutOfStockError::class)]
class ProductOutOfStockErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $id = Uuid::randomHex();
        $error = new ProductOutOfStockError(id: $id, name: 'Shirt');

        static::assertSame('The product Shirt is no longer available', $error->getMessage());
        static::assertSame('product-out-of-stock' . $id, $error->getId());
        static::assertSame('product-out-of-stock', $error->getMessageKey());
        static::assertSame(Error::LEVEL_ERROR, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame(['name' => 'Shirt'], $error->getParameters());
        static::assertSame('Shirt', $error->getName());
    }
}

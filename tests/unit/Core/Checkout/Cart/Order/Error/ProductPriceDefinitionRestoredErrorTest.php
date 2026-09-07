<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\Order\Error\ProductPriceDefinitionRestoredError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Assert\Serialization;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ProductPriceDefinitionRestoredError::class)]
class ProductPriceDefinitionRestoredErrorTest extends TestCase
{
    public function testErrorDescribesTheRestoredLineItem(): void
    {
        $error = new ProductPriceDefinitionRestoredError('line-item-id', 'Example product');

        static::assertSame('product-price-definition-restored-line-item-id', $error->getId());
        static::assertSame('product-price-definition-restored', $error->getMessageKey());
        static::assertSame(Error::LEVEL_WARNING, $error->getLevel());
        static::assertFalse($error->blockOrder());
        static::assertSame(['name' => 'Example product'], $error->getParameters());
        static::assertSame('line-item-id', $error->getLineItemId());
        static::assertSame('Example product', $error->getName());
        static::assertSame(
            'The saved price definition for product "Example product" was missing and has been restored from the order price. Review the price before saving the order.',
            $error->getMessage(),
        );

        Serialization::assertRoundTrip($error);
    }
}

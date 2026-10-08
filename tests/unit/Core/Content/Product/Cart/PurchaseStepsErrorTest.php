<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Content\Product\Cart\PurchaseStepsError;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(PurchaseStepsError::class)]
class PurchaseStepsErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $id = Uuid::randomHex();
        $error = new PurchaseStepsError(id: $id, name: 'Shirt', quantity: 4);

        static::assertSame(
            'Your input quantity does not match with the setup of the Shirt. The quantity was changed to 4',
            $error->getMessage()
        );
        static::assertSame('purchase-steps-quantity' . $id, $error->getId());
        static::assertSame('purchase-steps-quantity', $error->getMessageKey());
        static::assertSame(Error::LEVEL_WARNING, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame(['name' => 'Shirt', 'quantity' => 4], $error->getParameters());
    }
}

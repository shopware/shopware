<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\IncompleteLineItemError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(IncompleteLineItemError::class)]
class IncompleteLineItemErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new IncompleteLineItemError('line-item-key', 'price');

        static::assertSame('Line item "line-item-key" incomplete. Property "price" missing.', $error->getMessage());
        static::assertSame('line-item-key', $error->getId());
        static::assertSame('price', $error->getMessageKey());
        static::assertSame(20, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame(['key' => 'line-item-key', 'property' => 'price'], $error->getParameters());
    }
}

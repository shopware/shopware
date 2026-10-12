<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionExcludedError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionExcludedError::class)]
class PromotionExcludedErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new PromotionExcludedError('Summer Sale');

        static::assertSame('Promotion Summer Sale was excluded for cart.', $error->getMessage());
        static::assertSame('promotion-excluded', $error->getId());
        static::assertSame('promotion-excluded', $error->getMessageKey());
        static::assertSame(0, $error->getLevel());
        static::assertFalse($error->blockOrder());
        static::assertFalse($error->isPersistent());
        static::assertSame(['name' => 'Summer Sale'], $error->getParameters());
        static::assertSame('Summer Sale', $error->getName());
    }
}

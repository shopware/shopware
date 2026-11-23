<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\Error\AutoPromotionNotFoundError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(AutoPromotionNotFoundError::class)]
class AutoPromotionNotFoundErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new AutoPromotionNotFoundError('Summer Sale');

        static::assertSame('Promotion Summer Sale was no longer valid!', $error->getMessage());
        static::assertSame('auto-promotion-not-found-Summer Sale', $error->getId());
        static::assertSame('auto-promotion-not-found', $error->getMessageKey());
        static::assertSame(20, $error->getLevel());
        static::assertTrue($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame(['name' => 'Summer Sale'], $error->getParameters());
        static::assertSame('Summer Sale', $error->getName());
    }
}

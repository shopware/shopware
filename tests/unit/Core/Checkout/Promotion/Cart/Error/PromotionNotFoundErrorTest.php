<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotFoundError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionNotFoundError::class)]
class PromotionNotFoundErrorTest extends TestCase
{
    public function testConstruct(): void
    {
        $error = new PromotionNotFoundError('SUMMER10');

        static::assertSame('Promotion with code SUMMER10 not found!', $error->getMessage());
        static::assertSame('promotion-not-found', $error->getId());
        static::assertSame('promotion-not-found', $error->getMessageKey());
        static::assertSame(20, $error->getLevel());
        static::assertFalse($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame(['code' => 'SUMMER10'], $error->getParameters());
    }
}

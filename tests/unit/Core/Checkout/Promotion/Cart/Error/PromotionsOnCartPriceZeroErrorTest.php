<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionsOnCartPriceZeroError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionsOnCartPriceZeroError::class)]
class PromotionsOnCartPriceZeroErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $error = new PromotionsOnCartPriceZeroError(['Black Friday', 'Summer Sale']);

        static::assertSame('promotions-on-cart-price-zero-error', $error->getId());
        static::assertSame('promotions-on-cart-price-zero-error', $error->getMessageKey());
        static::assertSame(Error::LEVEL_NOTICE, $error->getLevel());
        static::assertFalse($error->blockOrder());
        static::assertFalse($error->isPersistent());
        static::assertSame(['Black Friday', 'Summer Sale'], $error->getPromotions());
        static::assertSame(['promotions' => 'Black Friday, Summer Sale'], $error->getParameters());
        static::assertSame(
            'Promotions Black Friday, Summer Sale were not applied because the cart does not contain any discountable products.',
            $error->getMessage()
        );
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionsOnCartPriceZeroError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionsOnCartPriceZeroError::class)]
class PromotionsOnCartPriceZeroErrorTest extends TestCase
{
    /**
     * @param string[] $promotions
     */
    #[DataProvider('promotionsProvider')]
    public function testConstruct(array $promotions, string $expectedList): void
    {
        $error = new PromotionsOnCartPriceZeroError($promotions);

        static::assertSame(
            \sprintf('Promotions %s were not applied because the cart does not contain any discountable products.', $expectedList),
            $error->getMessage()
        );
        static::assertSame('promotions-on-cart-price-zero-error', $error->getId());
        static::assertSame('promotions-on-cart-price-zero-error', $error->getMessageKey());
        static::assertSame(0, $error->getLevel());
        static::assertFalse($error->blockOrder());
        static::assertFalse($error->isPersistent());
        static::assertSame(['promotions' => $expectedList], $error->getParameters());
        static::assertSame($promotions, $error->getPromotions());
    }

    public static function promotionsProvider(): \Generator
    {
        yield 'single promotion' => [['Summer Sale'], 'Summer Sale'];
        yield 'several promotions' => [['Summer Sale', 'Winter Sale', 'Free Shipping'], 'Summer Sale, Winter Sale, Free Shipping'];
        yield 'no promotions' => [[], ''];
    }
}

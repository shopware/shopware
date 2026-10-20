<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionDiscountZeroValueError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionDiscountZeroValueError::class)]
class PromotionDiscountZeroValueErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $discountLineItem = new LineItem('discount-line-item-id', PromotionProcessor::LINE_ITEM_TYPE);
        $discountLineItem->setLabel('Summer Sale');

        $error = new PromotionDiscountZeroValueError($discountLineItem);

        static::assertSame('promotion-discount-zero-value-discount-line-item-id', $error->getId());
        static::assertSame('promotion-discount-zero-value', $error->getMessageKey());
        static::assertSame(Error::LEVEL_NOTICE, $error->getLevel());
        static::assertFalse($error->blockOrder());
        static::assertSame('Summer Sale', $error->getName());
        static::assertSame('Discount "Summer Sale" does not reduce the price of this cart.', $error->getMessage());
        static::assertSame([
            'name' => 'Summer Sale',
            'discountLineItemId' => 'discount-line-item-id',
        ], $error->getParameters());
    }

    public function testNoticeIsRaisedAgainOnEveryCartCalculation(): void
    {
        $error = new PromotionDiscountZeroValueError(new LineItem('discount-line-item-id', PromotionProcessor::LINE_ITEM_TYPE));

        static::assertFalse($error->isPersistent());
    }

    public function testNameFallsBackToLineItemIdWithoutLabel(): void
    {
        $discountLineItem = new LineItem('discount-line-item-id', PromotionProcessor::LINE_ITEM_TYPE);

        $error = new PromotionDiscountZeroValueError($discountLineItem);

        static::assertSame('discount-line-item-id', $error->getName());
    }
}

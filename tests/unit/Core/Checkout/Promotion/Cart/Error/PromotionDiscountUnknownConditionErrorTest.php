<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart\Error;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionDiscountUnknownConditionError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionDiscountUnknownConditionError::class)]
class PromotionDiscountUnknownConditionErrorTest extends TestCase
{
    public function testAPI(): void
    {
        $discountLineItem = new LineItem('discount-line-item-id', PromotionProcessor::LINE_ITEM_TYPE);
        $discountLineItem->setLabel('Summer Sale');

        $error = new PromotionDiscountUnknownConditionError($discountLineItem, 'cartHasPpPromotionItem');

        static::assertSame('promotion-discount-unknown-condition-discount-line-item-id', $error->getId());
        static::assertSame('promotion-discount-unknown-condition', $error->getMessageKey());
        static::assertSame(Error::LEVEL_WARNING, $error->getLevel());
        static::assertFalse($error->blockOrder());
        static::assertSame('Summer Sale', $error->getName());
        static::assertSame('cartHasPpPromotionItem', $error->getOriginalConditionName());
        static::assertSame(
            'Discount "Summer Sale" was removed: its condition "cartHasPpPromotionItem" is no longer available, e.g. because the extension providing it was uninstalled.',
            $error->getMessage()
        );
        static::assertSame([
            'name' => 'Summer Sale',
            'discountLineItemId' => 'discount-line-item-id',
            'originalConditionName' => 'cartHasPpPromotionItem',
        ], $error->getParameters());
    }

    public function testNameFallsBackToLineItemIdWithoutLabel(): void
    {
        $discountLineItem = new LineItem('discount-line-item-id', PromotionProcessor::LINE_ITEM_TYPE);

        $error = new PromotionDiscountUnknownConditionError($discountLineItem, 'cartHasPpPromotionItem');

        static::assertSame('discount-line-item-id', $error->getName());
    }
}

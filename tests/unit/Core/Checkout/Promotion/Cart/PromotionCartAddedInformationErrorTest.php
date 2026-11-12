<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCartAddedInformationError;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionCartAddedInformationError::class)]
class PromotionCartAddedInformationErrorTest extends TestCase
{
    #[DataProvider('labelProvider')]
    public function testConstruct(?string $label, string $expectedName): void
    {
        $discountLineItem = new LineItem('discount-line-item-id', LineItem::PROMOTION_LINE_ITEM_TYPE);
        $discountLineItem->setLabel($label);

        $error = new PromotionCartAddedInformationError($discountLineItem);

        static::assertSame(\sprintf('Discount %s has been added', $expectedName), $error->getMessage());
        static::assertSame('promotion-discount-added-discount-line-item-id', $error->getId());
        static::assertSame('promotion-discount-added', $error->getMessageKey());
        static::assertSame(0, $error->getLevel());
        static::assertFalse($error->blockOrder());
        static::assertTrue($error->isPersistent());
        static::assertSame([
            'name' => $expectedName,
            'discountLineItemId' => 'discount-line-item-id',
        ], $error->getParameters());
        static::assertSame($expectedName, $error->getName());
    }

    public static function labelProvider(): \Generator
    {
        yield 'labelled discount' => ['Summer Sale', 'Summer Sale'];
        yield 'discount without label' => [null, ''];
    }
}

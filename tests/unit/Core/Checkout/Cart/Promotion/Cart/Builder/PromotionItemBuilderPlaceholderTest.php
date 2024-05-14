<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(PromotionItemBuilder::class)]
class PromotionItemBuilderPlaceholderTest extends TestCase
{
    /**
     * This test verifies that the immutable LineItem Type from
     * the constructor is correctly used in the LineItem.
     */
    #[Group('promotions')]
    public function testLineItemType(): void
    {
        $builder = new PromotionItemBuilder();

        $item = $builder->buildPlaceholderItem('CODE-123');

        static::assertEquals(PromotionProcessor::LINE_ITEM_TYPE, $item->getType());
    }

    /**
     * This test verifies that we get a correct percentage price of 0
     * for our placeholder item. This is important to avoid any wrong
     * calculations or side effects that could modify the cart amount.
     */
    #[Group('promotions')]
    public function testDefaultPriceIsEmpty(): void
    {
        $builder = new PromotionItemBuilder();

        $item = $builder->buildPlaceholderItem('CODE-123');

        $expectedPriceDefinition = new PercentagePriceDefinition(0);

        static::assertEquals($expectedPriceDefinition, $item->getPriceDefinition());
    }

    /**
     * This one is the most important test.
     * It asserts that our applied code is added to the expected property referenceId of the line item.
     * When it is converted into a real promotion line item, this code is being used
     * to fetch that promotion.
     */
    #[Group('promotions')]
    public function testCodeValueInReferenceId(): void
    {
        $builder = new PromotionItemBuilder();

        $item = $builder->buildPlaceholderItem('CODE-123');

        static::assertEquals('CODE-123', $item->getReferencedId());
    }

    /**
     * This test verifies that we have our correct prefix in the key.
     * We use the code as key to avoid andy randomly generated UIDs.
     * But we still need to ensure the key is unique. To avoid any interferences
     * with other line items, we use a promotion prefix for this.
     */
    #[Group('promotions')]
    public function testKeyIsUnique(): void
    {
        $builder = new PromotionItemBuilder();

        $item = $builder->buildPlaceholderItem('CODE-123');

        static::assertEquals(Uuid::fromStringToHex('promotion-CODE-123'), $item->getId());
    }
}

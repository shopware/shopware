<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackagePicker;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\PackageFilter;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\SetGroupScopeFilter;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionDiscountUnknownConditionError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCalculator;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Container\AndRule;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\UnknownConditionRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionCalculator::class)]
class PromotionCalculatorTest extends TestCase
{
    private PromotionCalculator $promotionCalculator;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        // a packager without matching items yields a zero-value discount, exactly like a
        // price-definition filter that matches no line items
        $cartScopeDiscountPackager = $this->createMock(DiscountPackager::class);
        $cartScopeDiscountPackager->method('getMatchingItems')->willReturn(new DiscountPackageCollection([]));

        $this->promotionCalculator = new PromotionCalculator(
            $this->createMock(AmountCalculator::class),
            $this->createMock(AbsolutePriceCalculator::class),
            $this->createMock(LineItemGroupBuilder::class),
            $this->createMock(DiscountCompositionBuilder::class),
            $this->createMock(PackageFilter::class),
            $this->createMock(AdvancedPackagePicker::class),
            $this->createMock(SetGroupScopeFilter::class),
            $this->createMock(LineItemQuantitySplitter::class),
            $this->createMock(PercentagePriceCalculator::class),
            $cartScopeDiscountPackager,
            $this->createMock(DiscountPackager::class),
            $this->createMock(DiscountPackager::class),
        );

        $this->context = $this->createMock(SalesChannelContext::class);
    }

    public function testZeroValueDiscountWithUnknownConditionAddsWarning(): void
    {
        $discountItem = $this->createDiscountItem(new UnknownConditionRule(['_name' => 'unknownPluginRule']));
        $calculated = new Cart('calculated');

        $this->promotionCalculator->calculate(new LineItemCollection([$discountItem]), new Cart('original'), $calculated, $this->context, new CartBehavior());

        $errors = $calculated->getErrors()->filterInstance(PromotionDiscountUnknownConditionError::class);
        static::assertCount(1, $errors);

        $error = $errors->first();
        static::assertInstanceOf(PromotionDiscountUnknownConditionError::class, $error);
        static::assertSame('unknownPluginRule', $error->getOriginalConditionName());
        static::assertSame('Summer Sale', $error->getName());

        // the discount is still dropped, the warning only makes the removal visible
        static::assertCount(0, $calculated->getLineItems());
    }

    public function testZeroValueDiscountWithUnknownConditionNestedInContainerAddsWarning(): void
    {
        $filter = new AndRule([new OrRule([new UnknownConditionRule(['_name' => 'unknownPluginRule'])])]);
        $discountItem = $this->createDiscountItem($filter);
        $calculated = new Cart('calculated');

        $this->promotionCalculator->calculate(new LineItemCollection([$discountItem]), new Cart('original'), $calculated, $this->context, new CartBehavior());

        $errors = $calculated->getErrors()->filterInstance(PromotionDiscountUnknownConditionError::class);
        static::assertCount(1, $errors);

        $error = $errors->first();
        static::assertInstanceOf(PromotionDiscountUnknownConditionError::class, $error);
        static::assertSame('unknownPluginRule', $error->getOriginalConditionName());
    }

    public function testZeroValueDiscountWithoutUnknownConditionStaysSilent(): void
    {
        foreach ([null, new AndRule([new OrRule([])])] as $filter) {
            $discountItem = $this->createDiscountItem($filter);
            $calculated = new Cart('calculated');

            $this->promotionCalculator->calculate(new LineItemCollection([$discountItem]), new Cart('original'), $calculated, $this->context, new CartBehavior());

            static::assertCount(0, $calculated->getErrors());
        }
    }

    public function testZeroValueDiscountWithNonFilterablePriceDefinitionStaysSilent(): void
    {
        // a QuantityPriceDefinition cannot carry a filter, so there is no condition to inspect
        $discountItem = $this->createDiscountItem(null);
        $discountItem->setPriceDefinition(new QuantityPriceDefinition(10.0, new TaxRuleCollection()));
        $calculated = new Cart('calculated');

        $this->promotionCalculator->calculate(new LineItemCollection([$discountItem]), new Cart('original'), $calculated, $this->context, new CartBehavior());

        static::assertCount(0, $calculated->getErrors());
    }

    private function createDiscountItem(?Rule $filter): LineItem
    {
        $discountItem = new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE);
        $discountItem->setLabel('Summer Sale');
        $discountItem->setPriceDefinition(new PercentagePriceDefinition(-10, $filter));
        $discountItem->setPayload([
            'discountScope' => PromotionDiscountEntity::SCOPE_CART,
            'discountType' => PromotionDiscountEntity::TYPE_PERCENTAGE,
            'promotionId' => Uuid::randomHex(),
            'priority' => 1,
            'exclusions' => [],
            'preventCombination' => false,
        ]);

        return $discountItem;
    }
}

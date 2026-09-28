<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Promotion\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantityCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItemQuantitySplitter;
use Shopware\Core\Checkout\Cart\Price\AbsolutePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\AmountCalculator;
use Shopware\Core\Checkout\Cart\Price\PercentagePriceCalculator;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackagePicker;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\PackageFilter;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\SetGroupScopeFilter;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionDiscountUnknownConditionError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionDiscountZeroValueError;
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
        $this->promotionCalculator = $this->createPromotionCalculator();
        $this->context = static::createStub(SalesChannelContext::class);
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

    public function testAlreadyPreservedPromotionIsNotRecalculated(): void
    {
        $discountItem = $this->createDiscountItem(null);
        $discountItem->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_SET);

        $calculated = new Cart('calculated');
        $calculated->add($discountItem);

        $setScopeDiscountPackager = $this->createMock(DiscountPackager::class);
        $setScopeDiscountPackager->expects($this->never())->method('getMatchingItems');
        $this->promotionCalculator = $this->createPromotionCalculator($setScopeDiscountPackager);

        $this->promotionCalculator->calculate(
            new LineItemCollection([$discountItem]),
            new Cart('original'),
            $calculated,
            $this->context,
            new CartBehavior(),
        );

        static::assertSame($discountItem, $calculated->get($discountItem->getId()));
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

    public function testRedeemedCodeWithoutAnyDiscountValueAddsNotice(): void
    {
        $product = $this->createProduct();
        $discountItem = $this->createZeroValueDiscountItem('SUMMER');

        $calculated = new Cart('calculated');
        $calculated->add($product);

        $this->createPromotionCalculatorMatching($product)->calculate(
            new LineItemCollection([$discountItem]),
            new Cart('original'),
            $calculated,
            $this->context,
            new CartBehavior(),
        );

        $errors = $calculated->getErrors()->filterInstance(PromotionDiscountZeroValueError::class);
        static::assertCount(1, $errors);

        $error = $errors->first();
        static::assertInstanceOf(PromotionDiscountZeroValueError::class, $error);
        static::assertSame('Summer Sale', $error->getName());

        // the discount is still dropped, the notice only tells the customer why nothing happened
        static::assertNull($calculated->get($discountItem->getId()));
    }

    public function testAutomaticPromotionWithoutAnyDiscountValueStaysSilent(): void
    {
        $product = $this->createProduct();
        $discountItem = $this->createZeroValueDiscountItem(null);

        $calculated = new Cart('calculated');
        $calculated->add($product);

        $this->createPromotionCalculatorMatching($product)->calculate(
            new LineItemCollection([$discountItem]),
            new Cart('original'),
            $calculated,
            $this->context,
            new CartBehavior(),
        );

        static::assertCount(0, $calculated->getErrors());
    }

    public function testRedeemedCodeThatMatchesNoLineItemStaysSilent(): void
    {
        $discountItem = $this->createDiscountItem(null);
        $discountItem->setPayloadValue('code', 'SUMMER');
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

    private function createProduct(): LineItem
    {
        $product = new LineItem('product-id', LineItem::PRODUCT_LINE_ITEM_TYPE);
        $product->setPrice(new CalculatedPrice(10.0, 10.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        return $product;
    }

    private function createZeroValueDiscountItem(?string $code): LineItem
    {
        $discountItem = new LineItem(Uuid::randomHex(), PromotionProcessor::LINE_ITEM_TYPE);
        $discountItem->setLabel('Summer Sale');
        $discountItem->setReferencedId($code);
        $discountItem->setPriceDefinition(new AbsolutePriceDefinition(0.0));
        $discountItem->setPayload([
            'code' => $code,
            'discountScope' => PromotionDiscountEntity::SCOPE_CART,
            'discountType' => PromotionDiscountEntity::TYPE_ABSOLUTE,
            'promotionId' => Uuid::randomHex(),
            'priority' => 1,
            'exclusions' => [],
            'preventCombination' => false,
        ]);

        return $discountItem;
    }

    /**
     * A calculator whose cart scope packager matches the given product, so the discount is applied
     * to a real line item and only its value comes out as zero.
     */
    private function createPromotionCalculatorMatching(LineItem $product): PromotionCalculator
    {
        $cartScopeDiscountPackager = static::createStub(DiscountPackager::class);
        $cartScopeDiscountPackager->method('getMatchingItems')->willReturn(new DiscountPackageCollection([
            new DiscountPackage(new LineItemQuantityCollection([new LineItemQuantity($product->getId(), 1)])),
        ]));

        $lineItemQuantitySplitter = static::createStub(LineItemQuantitySplitter::class);
        $lineItemQuantitySplitter->method('split')->willReturnArgument(0);

        $absolutePriceCalculator = static::createStub(AbsolutePriceCalculator::class);
        $absolutePriceCalculator->method('calculate')->willReturn(new CalculatedPrice(0.0, 0.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $advancedRules = static::createStub(SetGroupScopeFilter::class);
        $advancedRules->method('filter')->willReturnArgument(1);

        $advancedPicker = static::createStub(AdvancedPackagePicker::class);
        $advancedPicker->method('pickItems')->willReturnArgument(1);

        $advancedFilter = static::createStub(PackageFilter::class);
        $advancedFilter->method('filterPackages')->willReturnArgument(1);

        $discountCompositionBuilder = static::createStub(DiscountCompositionBuilder::class);
        $discountCompositionBuilder->method('aggregateCompositionItems')->willReturnArgument(0);

        return new PromotionCalculator(
            static::createStub(AmountCalculator::class),
            $absolutePriceCalculator,
            static::createStub(LineItemGroupBuilder::class),
            $discountCompositionBuilder,
            $advancedFilter,
            $advancedPicker,
            $advancedRules,
            $lineItemQuantitySplitter,
            static::createStub(PercentagePriceCalculator::class),
            $cartScopeDiscountPackager,
            static::createStub(DiscountPackager::class),
            static::createStub(DiscountPackager::class),
        );
    }

    private function createPromotionCalculator(?DiscountPackager $setScopeDiscountPackager = null): PromotionCalculator
    {
        // a packager without matching items yields a zero-value discount, exactly like a
        // price-definition filter that matches no line items
        $cartScopeDiscountPackager = static::createStub(DiscountPackager::class);
        $cartScopeDiscountPackager->method('getMatchingItems')->willReturn(new DiscountPackageCollection([]));

        return new PromotionCalculator(
            static::createStub(AmountCalculator::class),
            static::createStub(AbsolutePriceCalculator::class),
            static::createStub(LineItemGroupBuilder::class),
            static::createStub(DiscountCompositionBuilder::class),
            static::createStub(PackageFilter::class),
            static::createStub(AdvancedPackagePicker::class),
            static::createStub(SetGroupScopeFilter::class),
            static::createStub(LineItemQuantitySplitter::class),
            static::createStub(PercentagePriceCalculator::class),
            $cartScopeDiscountPackager,
            static::createStub(DiscountPackager::class),
            $setScopeDiscountPackager ?? static::createStub(DiscountPackager::class),
        );
    }
}

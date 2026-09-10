<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart;

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
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Composition\DiscountCompositionBuilder;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackage;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackageCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountPackager;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\AdvancedPackagePicker;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\PackageFilter;
use Shopware\Core\Checkout\Promotion\Cart\Discount\Filter\SetGroupScopeFilter;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionExcludedError;
use Shopware\Core\Checkout\Promotion\Cart\Error\PromotionNotEligibleError;
use Shopware\Core\Checkout\Promotion\Cart\PromotionCalculator;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\PromotionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Framework\IdsCollection;
use Shopware\Core\Test\Stub\Rule\FalseRule;
use Shopware\Core\Test\Stub\Rule\TrueRule;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(PromotionCalculator::class)]
class PromotionCalculatorTest extends TestCase
{
    private IdsCollection $ids;

    private PromotionCalculator $promotionCalculator;

    protected function setUp(): void
    {
        $this->ids = new IdsCollection();

        $this->promotionCalculator = new PromotionCalculator(
            static::createStub(AmountCalculator::class),
            static::createStub(AbsolutePriceCalculator::class),
            static::createStub(LineItemGroupBuilder::class),
            static::createStub(DiscountCompositionBuilder::class),
            static::createStub(PackageFilter::class),
            static::createStub(AdvancedPackagePicker::class),
            static::createStub(SetGroupScopeFilter::class),
            static::createStub(LineItemQuantitySplitter::class),
            static::createStub(PercentagePriceCalculator::class),
            static::createStub(DiscountPackager::class),
            static::createStub(DiscountPackager::class),
            static::createStub(DiscountPackager::class)
        );
    }

    public function testThrowsExceptionWhenInvalidScopeDefinition(): void
    {
        $this->expectExceptionObject(PromotionException::invalidScopeDefinition('invalid-scope'));

        $lineItems = new LineItem($this->ids->get('line-item-1'), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItems->setPriceDefinition(new AbsolutePriceDefinition(50.0));
        $lineItems->setLabel('Product');

        $discountItem = $this->getDiscountItem('first-promotion')
            ->setPayloadValue('code', 'code-1')
            ->setPayloadValue('exclusions', ['second-promotion'])
            ->setPayloadValue('priority', 2)
            ->setPayloadValue('discountScope', 'invalid-scope');

        $cart = new Cart('promotion-test');
        $cart->addLineItems(new LineItemCollection([$lineItems]));

        $this->promotionCalculator->calculate(
            new LineItemCollection([$discountItem]),
            $cart,
            $cart,
            static::createStub(SalesChannelContext::class),
            new CartBehavior()
        );
    }

    public function testPromotionPrioritySorting(): void
    {
        $lineItems = new LineItem($this->ids->get('line-item-1'), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $lineItems->setPriceDefinition(new AbsolutePriceDefinition(50.0));
        $lineItems->setLabel('Product');

        $firstDiscountItem = $this->getDiscountItem('frist-promotion')
            ->setPayloadValue('code', 'code-1')
            ->setPayloadValue('exclusions', ['second-promotion'])
            ->setPayloadValue('priority', 2);

        $secondDiscountItem = $this->getDiscountItem('second-promotion')
            ->setPayloadValue('code', 'code-2')
            ->setPayloadValue('exclusions', ['frist-promotion'])
            ->setPayloadValue('priority', 1)
            ->setPriceDefinition(new AbsolutePriceDefinition(-20.0));

        $cart = new Cart('promotion-test');
        $cart->addLineItems(new LineItemCollection([$lineItems]));

        $this->promotionCalculator->calculate(
            new LineItemCollection([$secondDiscountItem, $firstDiscountItem]),
            $cart,
            $cart,
            static::createStub(SalesChannelContext::class),
            new CartBehavior()
        );

        static::assertCount(1, $cart->getErrors());
        $error = $cart->getErrors()->first();

        static::assertInstanceOf(PromotionExcludedError::class, $error);
        static::assertSame('Promotion second-promotion was excluded for cart.', $error->getMessage());
    }

    public function testAddDiscountWithPackages(): void
    {
        $lineItem1 = new LineItem($this->ids->get('line-item-1'), LineItem::PRODUCT_LINE_ITEM_TYPE, $this->ids->get('line-item-1'));
        $lineItem1->setPriceDefinition(new AbsolutePriceDefinition(50.0));
        $lineItem1->setLabel('Product 50 1');
        $lineItem1->setPrice(new CalculatedPrice(50.0, 50.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $lineItem2 = new LineItem($this->ids->get('line-item-2'), LineItem::PRODUCT_LINE_ITEM_TYPE, $this->ids->get('line-item-2'), 10);
        $lineItem2->setPriceDefinition(new AbsolutePriceDefinition(100.0));
        $lineItem2->setLabel('Product 100 10');
        $lineItem2->setPrice(new CalculatedPrice(10.0, 100.0, new CalculatedTaxCollection(), new TaxRuleCollection(), 10));

        $discountPackage = new DiscountPackage(new LineItemQuantityCollection([new LineItemQuantity($this->ids->get('line-item-1'), 1), new LineItemQuantity($this->ids->get('line-item-2'), 10)]));
        $cartPackager = $this->createMock(DiscountPackager::class);
        $cartPackager
            ->expects($this->once())
            ->method('getMatchingItems')
            ->willReturn(new DiscountPackageCollection([$discountPackage]));

        $lineItemQuantitySplitter = static::createStub(LineItemQuantitySplitter::class);
        $lineItemQuantitySplitter
            ->method('split')
            ->willReturnCallback(static fn (LineItem $item) => $item);

        $packageFilter = static::createStub(PackageFilter::class);
        $packageFilter
            ->method('filterPackages')
            ->willReturnCallback(static fn (DiscountLineItem $discount, DiscountPackageCollection $packages) => $packages);

        $advancedPackagePicker = static::createStub(AdvancedPackagePicker::class);
        $advancedPackagePicker
            ->method('pickItems')
            ->willReturnCallback(static fn (DiscountLineItem $discount, DiscountPackageCollection $packages) => $packages);

        $setGroupScopeFilter = static::createStub(SetGroupScopeFilter::class);
        $setGroupScopeFilter
            ->method('filter')
            ->willReturnCallback(static fn (DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context) => $packages);

        $absolutePriceCalculator = static::createStub(AbsolutePriceCalculator::class);
        $absolutePriceCalculator
            ->method('calculate')
            ->willReturnCallback(static function (float $price) {
                return new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection());
            });

        $calculator = new PromotionCalculator(
            static::createStub(AmountCalculator::class),
            $absolutePriceCalculator,
            static::createStub(LineItemGroupBuilder::class),
            static::createStub(DiscountCompositionBuilder::class),
            $packageFilter,
            $advancedPackagePicker,
            $setGroupScopeFilter,
            $lineItemQuantitySplitter,
            static::createStub(PercentagePriceCalculator::class),
            $cartPackager,
            static::createStub(DiscountPackager::class),
            static::createStub(DiscountPackager::class)
        );

        $salesChannelContext = static::createStub(SalesChannelContext::class);
        $cart = new Cart('promotion-test');
        $cart->addLineItems(new LineItemCollection([$lineItem1, $lineItem2]));
        $cart->setPrice(new CartPrice(150, 150, 150, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $discountItem = $this->getDiscountItem('promotion')
            ->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $collection = new LineItemCollection([$discountItem]);

        $calculator->calculate($collection, $cart, $cart, $salesChannelContext, new CartBehavior());

        static::assertNotNull($cart->getLineItems()->get($discountItem->getId()));
        static::assertSame(-10.0, $discountItem->getPrice()?->getTotalPrice());
        static::assertTrue($discountItem->hasPayloadValue('composition'));
    }

    public function testEnrichPackagesDropsPackageWithMissingLineItemInSplitItems(): void
    {
        $presentId = $this->ids->get('present-product');
        $missingId = $this->ids->get('stale-product');

        $presentItem = new LineItem($presentId, LineItem::PRODUCT_LINE_ITEM_TYPE, $presentId);
        $presentItem->setPriceDefinition(new AbsolutePriceDefinition(30.0));
        $presentItem->setLabel('Present product');
        $presentItem->setPrice(new CalculatedPrice(30.0, 30.0, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $packageWithStaleRef = new DiscountPackage(
            new LineItemQuantityCollection([
                new LineItemQuantity($presentId, 1),
                new LineItemQuantity($missingId, 1),
            ])
        );

        $cartPackager = static::createStub(DiscountPackager::class);
        $cartPackager
            ->method('getMatchingItems')
            ->willReturn(new DiscountPackageCollection([$packageWithStaleRef]));

        $lineItemQuantitySplitter = static::createStub(LineItemQuantitySplitter::class);
        $lineItemQuantitySplitter
            ->method('split')
            ->willReturnCallback(static fn (LineItem $item) => $item);

        $packageFilter = static::createStub(PackageFilter::class);
        $packageFilter
            ->method('filterPackages')
            ->willReturnCallback(static fn (DiscountLineItem $d, DiscountPackageCollection $p) => $p);

        $advancedPackagePicker = static::createStub(AdvancedPackagePicker::class);
        $advancedPackagePicker
            ->method('pickItems')
            ->willReturnCallback(static fn (DiscountLineItem $d, DiscountPackageCollection $p) => $p);

        $setGroupScopeFilter = static::createStub(SetGroupScopeFilter::class);
        $setGroupScopeFilter
            ->method('filter')
            ->willReturnCallback(static fn (DiscountLineItem $d, DiscountPackageCollection $p) => $p);

        $absolutePriceCalculator = static::createStub(AbsolutePriceCalculator::class);
        $absolutePriceCalculator
            ->method('calculate')
            ->willReturnCallback(static fn (float $price) => new CalculatedPrice($price, $price, new CalculatedTaxCollection(), new TaxRuleCollection()));

        $calculator = new PromotionCalculator(
            static::createStub(AmountCalculator::class),
            $absolutePriceCalculator,
            static::createStub(LineItemGroupBuilder::class),
            static::createStub(DiscountCompositionBuilder::class),
            $packageFilter,
            $advancedPackagePicker,
            $setGroupScopeFilter,
            $lineItemQuantitySplitter,
            static::createStub(PercentagePriceCalculator::class),
            $cartPackager,
            static::createStub(DiscountPackager::class),
            static::createStub(DiscountPackager::class)
        );

        $cart = new Cart('promotion-test');
        $cart->addLineItems(new LineItemCollection([$presentItem]));
        $cart->setPrice(new CartPrice(30, 30, 30, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $discountItem = $this->getDiscountItem('promo-stale-ref')
            ->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);

        $calculator->calculate(
            new LineItemCollection([$discountItem]),
            $cart,
            $cart,
            static::createStub(SalesChannelContext::class),
            new CartBehavior()
        );

        static::assertNull($cart->getLineItems()->get($discountItem->getId()));
    }

    public function testNotLoggedInAddsSpecificError(): void
    {
        $discountItem = $this->getDiscountItem('promotion')
            ->setPayloadValue('code', 'PROMO10')
            ->setPayloadValue('hasPersonaRestriction', true)
            ->setPayloadValue('conditionRuleIds', ['rule-id-1'])
            ->setRequirement(new FalseRule());

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $cart = new Cart('promotion-test');

        $this->promotionCalculator->calculate(
            new LineItemCollection([$discountItem]),
            $cart,
            $cart,
            $context,
            new CartBehavior()
        );

        static::assertCount(1, $cart->getErrors());
        $error = $cart->getErrors()->first();
        static::assertInstanceOf(PromotionNotEligibleError::class, $error);
        static::assertSame('promotion-not-eligible-not-logged-in', $error->getMessageKey());
    }

    public function testRuleIdsPassedToNotEligibleError(): void
    {
        $discountItem = $this->getDiscountItem('promotion')
            ->setPayloadValue('code', 'PROMO10')
            ->setPayloadValue('hasPersonaRestriction', false)
            ->setPayloadValue('conditionRuleIds', ['rule-id-1', 'rule-id-2'])
            ->setRequirement(new FalseRule());

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(null);

        $cart = new Cart('promotion-test');

        $this->promotionCalculator->calculate(
            new LineItemCollection([$discountItem]),
            $cart,
            $cart,
            $context,
            new CartBehavior()
        );

        static::assertCount(1, $cart->getErrors());
        $error = $cart->getErrors()->first();
        static::assertInstanceOf(PromotionNotEligibleError::class, $error);
        static::assertSame('promotion-not-eligible', $error->getMessageKey());
        static::assertSame(['rule-id-1', 'rule-id-2'], $error->getRuleIds());
    }

    public function testLoggedInCustomerWithPersonaRestrictionUsesRuleIds(): void
    {
        $discountItem = $this->getDiscountItem('promotion')
            ->setPayloadValue('code', 'PROMO10')
            ->setPayloadValue('hasPersonaRestriction', true)
            ->setPayloadValue('conditionRuleIds', ['rule-id-persona'])
            ->setRequirement(new FalseRule());

        $context = static::createStub(SalesChannelContext::class);
        $context->method('getCustomer')->willReturn(new CustomerEntity());

        $cart = new Cart('promotion-test');

        $this->promotionCalculator->calculate(
            new LineItemCollection([$discountItem]),
            $cart,
            $cart,
            $context,
            new CartBehavior()
        );

        static::assertCount(1, $cart->getErrors());
        $error = $cart->getErrors()->first();
        static::assertInstanceOf(PromotionNotEligibleError::class, $error);
        static::assertSame('promotion-not-eligible', $error->getMessageKey());
        static::assertSame(['rule-id-persona'], $error->getRuleIds());
    }

    public function testSpecificProductsErrorWhenNoPackagesAndProductRestricted(): void
    {
        $cart = $this->getCartWithProduct();

        $this->getCalculatorWithoutPackages()->calculate(
            new LineItemCollection([$this->getProductRestrictedDiscountItem(new FalseRule())]),
            $cart,
            $cart,
            static::createStub(SalesChannelContext::class),
            new CartBehavior()
        );

        static::assertCount(1, $cart->getErrors());
        $error = $cart->getErrors()->first();
        static::assertInstanceOf(PromotionNotEligibleError::class, $error);
        static::assertSame('promotion-not-eligible-specific-products', $error->getMessageKey());
    }

    public function testNoSpecificProductsErrorWhenRestrictedProductIsInCart(): void
    {
        $cart = $this->getCartWithProduct();

        $this->getCalculatorWithoutPackages()->calculate(
            new LineItemCollection([$this->getProductRestrictedDiscountItem(new TrueRule())]),
            $cart,
            $cart,
            static::createStub(SalesChannelContext::class),
            new CartBehavior()
        );

        static::assertCount(0, $cart->getErrors());
    }

    public function testNoSpecificProductsErrorWithoutDiscountableProducts(): void
    {
        $cart = new Cart('promotion-test');
        $cart->add(new LineItem($this->ids->get('bundle'), LineItem::CONTAINER_LINE_ITEM));
        $cart->setPrice(new CartPrice(100, 100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        $this->getCalculatorWithoutPackages()->calculate(
            new LineItemCollection([$this->getProductRestrictedDiscountItem(new FalseRule())]),
            $cart,
            $cart,
            static::createStub(SalesChannelContext::class),
            new CartBehavior()
        );

        static::assertCount(0, $cart->getErrors());
    }

    private function getCalculatorWithoutPackages(): PromotionCalculator
    {
        $cartPackager = static::createStub(DiscountPackager::class);
        $cartPackager
            ->method('getMatchingItems')
            ->willReturn(new DiscountPackageCollection([]));

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
            $cartPackager,
            static::createStub(DiscountPackager::class),
            static::createStub(DiscountPackager::class)
        );
    }

    private function getProductRestrictedDiscountItem(Rule $productFilter): LineItem
    {
        return $this->getDiscountItem('promotion')
            ->setPayloadValue('code', 'PROMO10')
            ->setPayloadValue('filter', [
                'considerAdvancedRules' => true,
                'sorterKey' => null,
                'applierKey' => 'ALL',
                'usageKey' => null,
                'pickerKey' => null,
            ])
            ->setPriceDefinition(new AbsolutePriceDefinition(-10.0, $productFilter));
    }

    private function getCartWithProduct(): Cart
    {
        $product = new LineItem($this->ids->get('line-item-1'), LineItem::PRODUCT_LINE_ITEM_TYPE);
        $product->setLabel('Product');
        $product->setPriceDefinition(new AbsolutePriceDefinition(50.0));

        $cart = new Cart('promotion-test');
        $cart->add($product);
        $cart->setPrice(new CartPrice(100, 100, 100, new CalculatedTaxCollection(), new TaxRuleCollection(), CartPrice::TAX_STATE_GROSS));

        return $cart;
    }

    private function getDiscountItem(string $promotionId): LineItem
    {
        $discountItemToBeExcluded = new LineItem($promotionId, PromotionProcessor::LINE_ITEM_TYPE);
        $discountItemToBeExcluded->setRequirement(null);
        $discountItemToBeExcluded->setPayloadValue('discountScope', PromotionDiscountEntity::SCOPE_CART);
        $discountItemToBeExcluded->setPayloadValue('discountType', PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discountItemToBeExcluded->setPayloadValue('exclusions', []);
        $discountItemToBeExcluded->setPayloadValue('promotionId', $promotionId);
        $discountItemToBeExcluded->setReferencedId($promotionId);
        $discountItemToBeExcluded->setLabel('Discount');
        $discountItemToBeExcluded->setPriceDefinition(new AbsolutePriceDefinition(-10.0));

        return $discountItemToBeExcluded;
    }
}

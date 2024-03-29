<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Cart\Builder;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemUnitPriceRule;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\Exception\UnknownPromotionDiscountTypeException;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(PromotionItemBuilder::class)]
class PromotionItemBuilderTest extends TestCase
{
    private PromotionEntity $promotion;

    /**
     * @var MockObject&SalesChannelContext
     */
    private SalesChannelContext $salesChannelContext;

    protected function setUp(): void
    {
        $this->promotion = new PromotionEntity();
        $this->promotion->setId('PR-1');
        $this->promotion->setPriority(1);
        $this->promotion->setUseCodes(false);
        $this->promotion->setUseIndividualCodes(false);
        $this->promotion->setUseSetGroups(false);

        $this->salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();

        $this->salesChannelContext->method('getContext')->willReturn($context);
    }

    /**
     * This test verifies that the immutable LineItem Type from
     * the constructor is correctly used in the LineItem.
     *
     * @throws CartException
     * @throws UnknownPromotionDiscountTypeException
     */
    #[Group('promotions')]
    public function testLineItemType(): void
    {
        $currencyFactor = random_int(0, mt_getrandmax()) / mt_getrandmax();

        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(50);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 'C1', $currencyFactor);

        static::assertEquals(PromotionProcessor::LINE_ITEM_TYPE, $item->getType());
    }

    /**
     * This test verifies that we always use the id of the
     * discount and not from the promotion for the item key.
     * If we have multiple discounts in a single promotion and use the promotion
     * id for the key, then we get duplicate key entries which leads to
     * errors like "line item not stackable".
     *
     * @throws CartException
     * @throws UnknownPromotionDiscountTypeException
     */
    #[Group('promotions')]
    public function testLineItemKey(): void
    {
        $builder = new PromotionItemBuilder();

        $currencyFactor = random_int(0, mt_getrandmax()) / mt_getrandmax();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(50);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 'C1', $currencyFactor);

        static::assertEquals('D5', $item->getId());
    }

    /**
     * This test verifies that our custom provided code is really
     * used in our referenceId. This is because we cannot simply use the
     * code from the promotion, because it might not be this one but one
     * of its thousand individual codes...thus its provided as separate argument
     *
     * @throws CartException
     * @throws UnknownPromotionDiscountTypeException
     */
    #[Group('promotions')]
    public function testLineItemReferenceId(): void
    {
        $discount = new PromotionDiscountEntity();
        $currencyFactor = random_int(0, mt_getrandmax()) / mt_getrandmax();

        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(50);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $item = (new PromotionItemBuilder())->buildDiscountLineItem('individual-123', $this->promotion, $discount, 'C1', $currencyFactor);

        static::assertEquals('individual-123', $item->getReferencedId());
    }

    /**
     * This test verifies that we get a correct percentage price
     * definition if our promotion is based on percentage values.
     * Also, we must not have a filter rule for this, if our eligible item ID list is empty.
     *
     * @throws CartException
     * @throws UnknownPromotionDiscountTypeException
     */
    #[Group('promotions')]
    public function testPriceTypePercentage(): void
    {
        $builder = new PromotionItemBuilder();

        $currencyFactor = random_int(0, mt_getrandmax()) / mt_getrandmax();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(10);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 'C1', $currencyFactor);

        $expectedPriceDefinition = new PercentagePriceDefinition(-10, null);

        static::assertEquals($expectedPriceDefinition, $item->getPriceDefinition());
    }

    /**
     * This test verifies that we get a correct absolute price
     * definition if our promotion is based on absolute values.
     * Also, we must not have a filter rule for this, if our eligible item ID list is empty.
     *
     * @throws CartException
     * @throws UnknownPromotionDiscountTypeException
     */
    #[Group('promotions')]
    public function testPriceTypeAbsolute(): void
    {
        $builder = new PromotionItemBuilder();

        $currencyFactor = random_int(0, mt_getrandmax()) / mt_getrandmax();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 'C1', $currencyFactor);

        $expectedPriceDefinition = new AbsolutePriceDefinition(-50 * $currencyFactor, null);

        static::assertEquals($expectedPriceDefinition, $item->getPriceDefinition());
    }

    /**
     * This test verifies that the correct discount filter
     * is set in the discountItemBuilder
     */
    #[Group('promotions')]
    public function testDiscountTargetFilter(): void
    {
        $builder = new PromotionItemBuilder();

        $currencyFactor = random_int(0, mt_getrandmax()) / mt_getrandmax();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);
        $discount->setConsiderAdvancedRules(true);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $amount = 100;
        $operator = '=';

        $discountFilter = $this->getFakeRule($amount, $operator);

        $discountRuleEntity = new RuleEntity();
        $discountRuleEntity->setId('foo');
        $discountRuleEntity->setPayload($discountFilter);

        $ruleCollection = new RuleCollection();
        $ruleCollection->add($discountRuleEntity);
        $discount->setDiscountRules($ruleCollection);

        $expectedRule = new OrRule();
        $expectedRule->addRule($discountFilter);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 'C1', $currencyFactor);

        static::assertInstanceOf(AbsolutePriceDefinition::class, $item->getPriceDefinition());
        static::assertEquals($expectedRule, $item->getPriceDefinition()->getFilter());
    }

    /**
     * This test verifies that the correct discount filter
     * is set in the discountItemBuilder
     */
    #[Group('promotions')]
    public function testDiscountTargetFilterIfDiscountRulesShouldBeIgnored(): void
    {
        $currencyFactor = random_int(0, mt_getrandmax()) / mt_getrandmax();

        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);
        $discount->setConsiderAdvancedRules(false);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $amount = 100;
        $operator = '=';

        $discountFilter = $this->getFakeRule($amount, $operator);

        $discountRuleEntity = new RuleEntity();
        $discountRuleEntity->setId('foo');
        $discountRuleEntity->setPayload($discountFilter);

        $ruleCollection = new RuleCollection();
        $ruleCollection->add($discountRuleEntity);
        $discount->setDiscountRules($ruleCollection);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 'C1', $currencyFactor);

        static::assertInstanceOf(AbsolutePriceDefinition::class, $item->getPriceDefinition());
        static::assertNull($item->getPriceDefinition()->getFilter());
    }

    /**
     * This test verifies that the correct discount filter
     * is set in the discountItemBuilder if discount rules are empty
     */
    #[Group('promotions')]
    public function testDiscountTargetFilterIfDiscountRulesAreEmpty(): void
    {
        $builder = new PromotionItemBuilder();

        $currencyFactor = random_int(0, mt_getrandmax()) / mt_getrandmax();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);
        $discount->setConsiderAdvancedRules(true);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $ruleCollection = new RuleCollection();
        $discount->setDiscountRules($ruleCollection);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 'C1', $currencyFactor);

        static::assertInstanceOf(AbsolutePriceDefinition::class, $item->getPriceDefinition());
        static::assertNull($item->getPriceDefinition()->getFilter());
    }

    /**
     * This test verifies that the correct currency price value is applied to
     * discount
     */
    #[Group('promotions')]
    public function testDiscountCurrencyCustomPrices(): void
    {
        $builder = new PromotionItemBuilder();

        $currencyFactor = random_int(0, mt_getrandmax()) / mt_getrandmax();
        $standardDiscountValue = 50;
        $currencyDiscountValue = 10;

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue($standardDiscountValue);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $currency = new CurrencyEntity();
        $currency->setId('C1');

        $advancedPrice = new PromotionDiscountPriceEntity();
        $advancedPrice->setUniqueIdentifier(Uuid::randomHex());
        $advancedPrice->setCurrency($currency);
        $advancedPrice->setCurrencyId($currency->getId());
        $advancedPrice->setPrice($currencyDiscountValue);

        $advancedPricesCollection = new PromotionDiscountPriceCollection([]);
        $advancedPricesCollection->add($advancedPrice);

        $discount->setPromotionDiscountPrices($advancedPricesCollection);

        $this->salesChannelContext->method('getCurrency')->willReturn($currency);

        $item = $builder->buildDiscountLineItem('code', $this->promotion, $discount, 'C1', $currencyFactor);

        $expectedPrice = -1 * $currencyDiscountValue;

        $expectedPriceDefinition = new AbsolutePriceDefinition($expectedPrice, null);

        static::assertEquals($expectedPriceDefinition, $item->getPriceDefinition());
    }

    /**
     * This test verifies that the currency price is calculated by factor if currency couldn't be found in
     * advanced discount prices.
     */
    #[Group('promotions')]
    public function testDiscountCurrencyCustomPricesMissingAdvancedPrice(): void
    {
        $builder = new PromotionItemBuilder();

        $currencyFactor = random_int(0, mt_getrandmax()) / mt_getrandmax();
        $standardDiscountValue = 50;
        $currencyDiscountValue = 10;

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue($standardDiscountValue);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $currency = new CurrencyEntity();
        $currency->setId('C1');

        $advancedPrice = new PromotionDiscountPriceEntity();
        $advancedPrice->setUniqueIdentifier(Uuid::randomHex());
        $advancedPrice->setCurrency($currency);
        $advancedPrice->setCurrencyId($currency->getId());
        $advancedPrice->setPrice($currencyDiscountValue);

        $advancedPricesCollection = new PromotionDiscountPriceCollection([]);
        $advancedPricesCollection->add($advancedPrice);

        $discount->setPromotionDiscountPrices($advancedPricesCollection);

        $this->salesChannelContext->method('getCurrency')->willReturn($currency);

        $item = $builder->buildDiscountLineItem('code', $this->promotion, $discount, 'D1', $currencyFactor);

        $expectedPrice = -1 * $standardDiscountValue * $currencyFactor;

        $expectedPriceDefinition = new AbsolutePriceDefinition($expectedPrice, null);

        static::assertEquals($expectedPriceDefinition, $item->getPriceDefinition());
    }

    /**
     * This test verifies that we have a backward compatibility.
     * Our currency factors is optional and should have 1.0 as default
     * if not provided as argument.
     * We just build a new discount line item and make sure the price
     * definition has our price * 1.0 as factor...so just the original price :)
     * Please note that factors and absolute price definitions will only
     * be available on "amount" discounts...so no percentage...
     *
     * @throws CartException
     * @throws UnknownPromotionDiscountTypeException
     */
    #[DataProvider('getDefaultCurrencyDataProvider')]
    #[Group('promotions')]
    public function testDefaultCurrencyFactor(string $type): void
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType($type);
        $discount->setValue(50);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $builder = new PromotionItemBuilder();

        $item = $builder->buildDiscountLineItem(
            '',
            $this->promotion,
            $discount,
            'C1'
        );

        /** @var AbsolutePriceDefinition $definition */
        $definition = $item->getPriceDefinition();

        static::assertEquals(-50, $definition->getPrice());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function getDefaultCurrencyDataProvider(): array
    {
        return [
            'absolute' => [PromotionDiscountEntity::TYPE_ABSOLUTE],
            'fixed' => [PromotionDiscountEntity::TYPE_FIXED],
            'fixed_unit' => [PromotionDiscountEntity::TYPE_FIXED_UNIT],
        ];
    }

    /**
     * just get a ruleEntity with ID R1
     */
    private function getFakeRule(int $amount, string $operator): LineItemUnitPriceRule
    {
        return (new LineItemUnitPriceRule())->assign(['amount' => $amount, 'operator' => $operator]);
    }
}

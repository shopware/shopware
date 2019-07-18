<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Cart\Builder;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\AbsolutePriceDefinition;
use Shopware\Core\Checkout\Cart\Price\Struct\PercentagePriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\LineItemUnitPriceRule;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\Cart\PromotionProcessor;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionItemBuilderTest extends TestCase
{
    /** @var PromotionEntity */
    private $promotion = null;

    /** @var MockObject */
    private $salesChannelContext = null;

    /** @var MockObject */
    private $context = null;

    public function setUp(): void
    {
        $this->promotion = new PromotionEntity();
        $this->promotion->setId('PR-1');
        $this->promotion->setUseCodes(false);

        $this->salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $this->context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->context->method('getCurrencyPrecision')->willReturn(3);

        $this->salesChannelContext->method('getContext')->willReturn($this->context);
    }

    /**
     * This test verifies that the immutable LineItem Type from
     * the constructor is correctly used in the LineItem.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     */
    public function testLineItemType()
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(50);

        $item = $builder->buildDiscountLineItem($this->promotion, $discount, $this->salesChannelContext);

        static::assertEquals(PromotionProcessor::LINE_ITEM_TYPE, $item->getType());
    }

    /**
     * This test verifies that we always use the id of the
     * discount and not from the promotion for the item key.
     * If we have multiple discounts in a single promotion and use the promotion
     * id for the key, then we get duplicate key entries which leads to
     * errors like "line item not stackable".
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     */
    public function testLineItemKey()
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(50);

        $item = $builder->buildDiscountLineItem($this->promotion, $discount, $this->salesChannelContext);

        static::assertEquals('D5', $item->getId());
    }

    /**
     * This test verifies that we get a correct percentage price
     * definition if our promotion is based on percentage values.
     * Also, we must not have a filter rule for this, if our eligible item ID list is empty.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     */
    public function testPriceTypePercentage()
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(10);

        $precision = $this->salesChannelContext->getContext()->getCurrencyPrecision();

        $item = $builder->buildDiscountLineItem($this->promotion, $discount, $this->salesChannelContext);

        $expectedPriceDefinition = new PercentagePriceDefinition(-10, $precision, null);

        static::assertEquals($expectedPriceDefinition, $item->getPriceDefinition());
    }

    /**
     * This test verifies that we get a correct absolute price
     * definition if our promotion is based on absolute values.
     * Also, we must not have a filter rule for this, if our eligible item ID list is empty.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     */
    public function testPriceTypeAbsolute()
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);

        /** @var int $precision */
        $precision = $this->salesChannelContext->getContext()->getCurrencyPrecision();

        $item = $builder->buildDiscountLineItem($this->promotion, $discount, $this->salesChannelContext);

        $expectedPriceDefinition = new AbsolutePriceDefinition(-50, $precision, null);

        static::assertEquals($expectedPriceDefinition, $item->getPriceDefinition());
    }

    /**
     * This test verifies that the correct discount filter
     * is set in the discountItemBuilder
     *
     * @test
     * @group promotions
     */
    public function testDiscountTargetFilter()
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);
        $discount->setConsiderAdvancedRules(true);

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

        $item = $builder->buildDiscountLineItem($this->promotion, $discount, $this->salesChannelContext);

        static::assertEquals($expectedRule, $item->getPriceDefinition()->getFilter());
    }

    /**
     * This test verifies that the correct discount filter
     * is set in the discountItemBuilder
     *
     * @test
     * @group promotions
     */
    public function testDiscountTargetFilterIfDiscountRulesShouldBeIgnored()
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);
        $discount->setConsiderAdvancedRules(false);

        $amount = 100;
        $operator = '=';

        $discountFilter = $this->getFakeRule($amount, $operator);

        $discountRuleEntity = new RuleEntity();
        $discountRuleEntity->setId('foo');
        $discountRuleEntity->setPayload($discountFilter);

        $ruleCollection = new RuleCollection();
        $ruleCollection->add($discountRuleEntity);
        $discount->setDiscountRules($ruleCollection);

        $item = $builder->buildDiscountLineItem($this->promotion, $discount, $this->salesChannelContext);

        static::assertNull($item->getPriceDefinition()->getFilter());
    }

    /**
     * This test verifies that the correct discount filter
     * is set in the discountItemBuilder if discount rules are empty
     *
     * @test
     * @group promotions
     */
    public function testDiscountTargetFilterIfDiscountRulesAreEmpty()
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);
        $discount->setConsiderAdvancedRules(true);

        $ruleCollection = new RuleCollection();
        $discount->setDiscountRules($ruleCollection);

        $item = $builder->buildDiscountLineItem($this->promotion, $discount, $this->salesChannelContext);

        static::assertNull($item->getPriceDefinition()->getFilter());
    }

    /**
     * This test verifies that the correct currency price value is applied to
     * discount
     *
     * @test
     * @group promotions
     */
    public function testDiscountCurrencyCustomPrices()
    {
        $builder = new PromotionItemBuilder('My-TYPE');

        $standardDiscountValue = 50;
        $currencyDiscountValue = 10;

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue($standardDiscountValue);

        $currency = new CurrencyEntity();
        $currency->setId('currency');

        $advancedPrice = new PromotionDiscountPriceEntity();
        $advancedPrice->setUniqueIdentifier(Uuid::randomHex());
        $advancedPrice->setCurrency($currency);
        $advancedPrice->setCurrencyId($currency->getId());
        $advancedPrice->setPrice($currencyDiscountValue);

        $advancedPricesCollection = new PromotionDiscountPriceCollection([]);
        $advancedPricesCollection->add($advancedPrice);

        $discount->setPromotionDiscountPrices($advancedPricesCollection);

        $this->salesChannelContext->method('getCurrency')->willReturn($currency);

        $item = $builder->buildDiscountLineItem($this->promotion, $discount, $this->salesChannelContext);

        $expectedPrice = -1 * $currencyDiscountValue;

        /** @var int $precision */
        $precision = $this->salesChannelContext->getContext()->getCurrencyPrecision();

        $expectedPriceDefinition = new AbsolutePriceDefinition($expectedPrice, $precision, null);

        static::assertEquals($expectedPriceDefinition, $item->getPriceDefinition());
    }

    /**
     * This test verifies that we have a correct payload
     * including our max value from our discount, when building
     * a new line item for our cart.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Promotion\Exception\UnknownPromotionDiscountTypeException
     */
    public function testPercentagePayloadWithoutAdvancedPrices()
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('P123');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(50);
        $discount->setMaxValue(23.0);

        $builder = new PromotionItemBuilder('My-TYPE');

        /** @var LineItem $item */
        $item = $builder->buildDiscountLineItem($this->promotion, $discount, $this->salesChannelContext);

        $expected = [
            'promotionId' => 'PR-1',
            'discountType' => 'percentage',
            'value' => 50,
            'maxValue' => 23,
            'discountId' => 'P123',
        ];

        static::assertEquals($expected, $item->getPayload());
    }

    /**
     * This test verifies that we have our max value from
     * the currency in our payload and not the one from
     * our discount entity.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Promotion\Exception\UnknownPromotionDiscountTypeException
     */
    public function testPercentagePayloadMaxValueWithAdvancedPrices()
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(40);
        $discount->setMaxValue(30.0);

        $currency = new CurrencyEntity();
        $currency->setId('currency');
        $this->salesChannelContext->method('getCurrency')->willReturn($currency);

        $advancedPrice = new PromotionDiscountPriceEntity();
        $advancedPrice->setUniqueIdentifier(Uuid::randomHex());
        $advancedPrice->setCurrency($currency);
        $advancedPrice->setCurrencyId($currency->getId());
        $advancedPrice->setPrice(20);
        $discount->setPromotionDiscountPrices(new PromotionDiscountPriceCollection([$advancedPrice]));

        $builder = new PromotionItemBuilder('My-TYPE');

        /** @var LineItem $item */
        $item = $builder->buildDiscountLineItem($this->promotion, $discount, $this->salesChannelContext);

        static::assertEquals(20, $item->getPayload()['maxValue']);
    }

    /**
     * This test verifies that we have our max value for
     * absolute discounts is null. This feature is not available
     * for absolute disocunts - only percentage discounts.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Promotion\Exception\UnknownPromotionDiscountTypeException
     */
    public function testAbsolutePayloadMaxValueIsNull()
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(40);
        $discount->setMaxValue(30.0);

        $builder = new PromotionItemBuilder('My-TYPE');

        /** @var LineItem $item */
        $item = $builder->buildDiscountLineItem($this->promotion, $discount, $this->salesChannelContext);

        static::assertEquals('', $item->getPayload()['maxValue']);
    }

    /**
     * just get a ruleEntity with ID R1
     *
     * @return RuleEntity
     */
    private function getFakeRule(int $amount, string $operator): LineItemUnitPriceRule
    {
        $productRule = (new LineItemUnitPriceRule())->assign(['amount' => $amount, 'operator' => $operator]);

        return $productRule;
    }
}

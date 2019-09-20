<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Cart\Builder;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
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
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionItemBuilderTest extends TestCase
{
    /**
     * @var PromotionEntity
     */
    private $promotion;

    /**
     * @var MockObject
     */
    private $salesChannelContext;

    public function setUp(): void
    {
        $this->promotion = new PromotionEntity();
        $this->promotion->setId('PR-1');
        $this->promotion->setUseCodes(false);
        $this->promotion->setUseSetGroups(false);

        $this->salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $context->method('getCurrencyPrecision')->willReturn(3);

        $this->salesChannelContext->method('getContext')->willReturn($context);
    }

    /**
     * This test verifies that the immutable LineItem Type from
     * the constructor is correctly used in the LineItem.
     *
     * @group promotions
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws UnknownPromotionDiscountTypeException
     */
    public function testLineItemType(): void
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(50);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, 'C1');

        static::assertSame(PromotionProcessor::LINE_ITEM_TYPE, $item->getType());
    }

    /**
     * This test verifies that we always use the id of the
     * discount and not from the promotion for the item key.
     * If we have multiple discounts in a single promotion and use the promotion
     * id for the key, then we get duplicate key entries which leads to
     * errors like "line item not stackable".
     *
     * @group promotions
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws UnknownPromotionDiscountTypeException
     */
    public function testLineItemKey(): void
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(50);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, 'C1');

        static::assertSame('D5', $item->getId());
    }

    /**
     * This test verifies that our custom provided code is really
     * used in our referenceId. This is because we cannot simply use the
     * code from the promotion, because it might not be this one but one
     * of its thousand individual codes...thus its provided as separate argument
     *
     * @group promotions
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws UnknownPromotionDiscountTypeException
     */
    public function testLineItemReferenceId(): void
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(50);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $item = (new PromotionItemBuilder())->buildDiscountLineItem('individual-123', $this->promotion, $discount, 1, 'C1');

        static::assertSame('individual-123', $item->getReferencedId());
    }

    /**
     * This test verifies that we get a correct percentage price
     * definition if our promotion is based on percentage values.
     * Also, we must not have a filter rule for this, if our eligible item ID list is empty.
     *
     * @group promotions
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws UnknownPromotionDiscountTypeException
     */
    public function testPriceTypePercentage(): void
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(10);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $precision = $this->salesChannelContext->getContext()->getCurrencyPrecision();
        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, $precision, 'C1');

        $expectedPriceDefinition = new PercentagePriceDefinition(-10, $precision, null);

        static::assertEquals($expectedPriceDefinition, $item->getPriceDefinition());
    }

    /**
     * This test verifies that we get a correct absolute price
     * definition if our promotion is based on absolute values.
     * Also, we must not have a filter rule for this, if our eligible item ID list is empty.
     *
     * @group promotions
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws UnknownPromotionDiscountTypeException
     */
    public function testPriceTypeAbsolute(): void
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        /** @var int $precision */
        $precision = $this->salesChannelContext->getContext()->getCurrencyPrecision();
        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, $precision, 'C1');

        $expectedPriceDefinition = new AbsolutePriceDefinition(-50, $precision, null);

        static::assertEquals($expectedPriceDefinition, $item->getPriceDefinition());
    }

    /**
     * This test verifies that the correct discount filter
     * is set in the discountItemBuilder
     *
     * @group promotions
     */
    public function testDiscountTargetFilter(): void
    {
        $builder = new PromotionItemBuilder();

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

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, 'C1');

        static::assertEquals($expectedRule, $item->getPriceDefinition()->getFilter());
    }

    /**
     * This test verifies that the correct discount filter
     * is set in the discountItemBuilder
     *
     * @group promotions
     */
    public function testDiscountTargetFilterIfDiscountRulesShouldBeIgnored(): void
    {
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

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, 'C1');

        static::assertNull($item->getPriceDefinition()->getFilter());
    }

    /**
     * This test verifies that the correct discount filter
     * is set in the discountItemBuilder if discount rules are empty
     *
     * @group promotions
     */
    public function testDiscountTargetFilterIfDiscountRulesAreEmpty(): void
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);
        $discount->setConsiderAdvancedRules(true);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $ruleCollection = new RuleCollection();
        $discount->setDiscountRules($ruleCollection);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, 'C1');

        static::assertNull($item->getPriceDefinition()->getFilter());
    }

    /**
     * This test verifies that we have the correct payload in our
     * discount line item. this is used to identify the promotion behind it.
     * It's also used as reference to individual codes that get marked as redeemed
     * in the event subscriber, when the order is created.
     *
     * @group promotions
     */
    public function testLineItemPayload(): void
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);
        $discount->setConsiderAdvancedRules(true);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $ruleCollection = new RuleCollection();
        $discount->setDiscountRules($ruleCollection);

        $item = $builder->buildDiscountLineItem('my-Code-123', $this->promotion, $discount, 1, 'C1');

        $expected = [
            'promotionId' => 'PR-1',
            'discountId' => 'D5',
            'discountType' => 'absolute',
            'code' => 'my-Code-123',
            'value' => '50',
            'maxValue' => '',
            'discountScope' => 'cart',
        ];

        static::assertSame($expected, $item->getPayload());
    }

    /**
     * This test verifies that the correct currency price value is applied to
     * discount
     *
     * @group promotions
     */
    public function testDiscountCurrencyCustomPrices(): void
    {
        $builder = new PromotionItemBuilder();

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

        /** @var int $precision */
        $precision = $this->salesChannelContext->getContext()->getCurrencyPrecision();

        $item = $builder->buildDiscountLineItem('code', $this->promotion, $discount, $precision, 'C1');

        $expectedPrice = -1 * $currencyDiscountValue;

        $expectedPriceDefinition = new AbsolutePriceDefinition($expectedPrice, $precision, null);

        static::assertEquals($expectedPriceDefinition, $item->getPriceDefinition());
    }

    /**
     * This test verifies that we have a correct payload
     * including our max value from our discount, when building
     * a new line item for our cart.
     *
     * @group promotions
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws UnknownPromotionDiscountTypeException
     */
    public function testPercentagePayloadWithoutAdvancedPrices(): void
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('P123');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(50);
        $discount->setMaxValue(23.0);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        /** @var LineItem $item */
        $item = (new PromotionItemBuilder())->buildDiscountLineItem('my-code', $this->promotion, $discount, 1, Defaults::CURRENCY);

        $expected = [
            'promotionId' => 'PR-1',
            'discountId' => 'P123',
            'discountType' => 'percentage',
            'code' => 'my-code',
            'value' => '50',
            'maxValue' => '23',
            'discountScope' => 'cart',
        ];

        static::assertSame($expected, $item->getPayload());
    }

    /**
     * This test verifies that we have our max value from
     * the currency in our payload and not the one from
     * our discount entity.
     *
     * @group promotions
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws UnknownPromotionDiscountTypeException
     */
    public function testPercentagePayloadMaxValueWithAdvancedPrices(): void
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(40);
        $discount->setMaxValue(30.0);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $currency = new CurrencyEntity();
        $currency->setId('currency');
        $this->salesChannelContext->method('getCurrency')->willReturn($currency);

        $advancedPrice = new PromotionDiscountPriceEntity();
        $advancedPrice->setUniqueIdentifier(Uuid::randomHex());
        $advancedPrice->setCurrency($currency);
        $advancedPrice->setCurrencyId($currency->getId());
        $advancedPrice->setPrice(20);
        $discount->setPromotionDiscountPrices(new PromotionDiscountPriceCollection([$advancedPrice]));

        /** @var LineItem $item */
        $item = (new PromotionItemBuilder())->buildDiscountLineItem('', $this->promotion, $discount, 1, $currency->getId());

        static::assertSame(20, (int) $item->getPayload()['maxValue']);
    }

    /**
     * This test verifies that we have our max value for
     * absolute discounts is null. This feature is not available
     * for absolute disocunts - only percentage discounts.
     *
     * @group promotions
     *
     * @throws InvalidPayloadException
     * @throws InvalidQuantityException
     * @throws UnknownPromotionDiscountTypeException
     */
    public function testAbsolutePayloadMaxValueIsNull(): void
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(40);
        $discount->setMaxValue(30.0);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        /** @var LineItem $item */
        $item = (new PromotionItemBuilder())->buildDiscountLineItem('', $this->promotion, $discount, 1, Defaults::CURRENCY);

        static::assertSame('', $item->getPayload()['maxValue']);
    }

    /**
     * This test verifies that the correct payload in the lineItem
     * by the discountItemBuilder
     *
     * @group promotions
     */
    public function testDiscountPayloadValues(): void
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);
        $discount->setConsiderAdvancedRules(false);
        $discount->setScope(PromotionDiscountEntity::SCOPE_DELIVERY);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, Defaults::CURRENCY);

        static::assertTrue($item->hasPayloadValue('promotionId'), 'We are expecting the promotionId as payload value');
        static::assertTrue($item->hasPayloadValue('discountId'), 'We are expecting the discountId as payload value');
        static::assertTrue($item->hasPayloadValue('discountType'), 'We are expecting the discountType as payload value');
        static::assertTrue($item->hasPayloadValue('discountScope'), 'We are expecting the discount scope as payload value');
        static::assertSame($this->promotion->getId(), $item->getPayloadValue('promotionId'), 'Wrong value in payload key promotionId');
        static::assertSame($discount->getId(), $item->getPayloadValue('discountId'), 'Wrong value in payload key discountId');
        static::assertSame($discount->getType(), $item->getPayloadValue('discountType'), 'Wrong value in payload key discountType');
        static::assertSame($discount->getScope(), $item->getPayloadValue('discountScope'), 'Wrong value in payload key scope');
    }

    /**
     * just get a ruleEntity with ID R1
     */
    private function getFakeRule(int $amount, string $operator): LineItemUnitPriceRule
    {
        $productRule = (new LineItemUnitPriceRule())->assign(['amount' => $amount, 'operator' => $operator]);

        return $productRule;
    }
}

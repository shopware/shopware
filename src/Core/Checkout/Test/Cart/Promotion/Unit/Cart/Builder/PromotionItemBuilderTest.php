<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Cart\Builder;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
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

        static::assertEquals(PromotionProcessor::LINE_ITEM_TYPE, $item->getType());
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

        static::assertEquals('D5', $item->getId());
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

        static::assertEquals('individual-123', $item->getReferencedId());
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
     * just get a ruleEntity with ID R1
     */
    private function getFakeRule(int $amount, string $operator): LineItemUnitPriceRule
    {
        $productRule = (new LineItemUnitPriceRule())->assign(['amount' => $amount, 'operator' => $operator]);

        return $productRule;
    }
}

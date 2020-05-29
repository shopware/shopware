<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Unit\Cart\Builder;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemUnitPriceRule;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscount\PromotionDiscountEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionDiscountPrice\PromotionDiscountPriceEntity;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupCollection;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSetGroup\PromotionSetGroupEntity;
use Shopware\Core\Checkout\Promotion\Cart\PromotionItemBuilder;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\Currency\CurrencyEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PromotionItemBuilderPayloadTest extends TestCase
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
        $this->promotion->setUseSetGroups(false);

        $this->salesChannelContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();
        $this->context = $this->getMockBuilder(Context::class)->disableOriginalConstructor()->getMock();
        $this->context->method('getCurrencyPrecision')->willReturn(3);

        $this->salesChannelContext->method('getContext')->willReturn($this->context);
    }

    /**
     * This test verifies that we have the correct payload in our
     * discount line item. this is used to identify the promotion behind it.
     * It's also used as reference to individual codes that get marked as redeemed
     * in the event subscriber, when the order is created.
     *
     * @test
     * @group promotions
     */
    public function testPayloadStructureBasic(): void
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
            'code' => 'my-Code-123',
            'discountType' => 'absolute',
            'value' => '50',
            'maxValue' => '',
            'discountScope' => 'cart',
            'setGroups' => [],
            'groupId' => '',
            'filter' => [
                'sorterKey' => null,
                'applierKey' => null,
                'usageKey' => null,
            ],
            'exclusions' => [],
        ];

        static::assertEquals($expected, $item->getPayload());
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
    public function testPayloadPercentageWithoutAdvancedPrices(): void
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('P123');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(50);
        $discount->setMaxValue(23.0);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $builder = new PromotionItemBuilder();

        $item = $builder->buildDiscountLineItem('my-code', $this->promotion, $discount, 1, Defaults::CURRENCY);

        $expected = [
            'promotionId' => 'PR-1',
            'discountType' => 'percentage',
            'value' => '50',
            'maxValue' => '23',
            'discountId' => 'P123',
            'code' => 'my-code',
            'discountScope' => 'cart',
            'setGroups' => [],
            'groupId' => '',
            'filter' => [
                'sorterKey' => null,
                'applierKey' => null,
                'usageKey' => null,
            ],
            'exclusions' => [],
        ];

        static::assertEquals($expected, $item->getPayload());
    }

    /**
     * This test verifies that we have an existing groupId payload entry
     * if we set the scope to SetGroup and assign a single group.
     * The group id will be used from the scope suffix. e.g. "setgroup-id123"
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Promotion\Exception\UnknownPromotionDiscountTypeException
     */
    public function testPayloadHasGroupIdOnSetGroupScope(): void
    {
        $groupId = 'id123';

        $discount = new PromotionDiscountEntity();
        $discount->setId('P123');
        $discount->setValue(10);
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setScope(PromotionDiscountEntity::SCOPE_SETGROUP . '-' . $groupId);

        $builder = new PromotionItemBuilder();

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, Defaults::CURRENCY);

        static::assertEquals($groupId, $item->getPayload()['groupId']);
    }

    /**
     * This test verifies that we have a correct payload
     * structure for our assigned setGroups.
     * So we fake a new SetGroup including a rule collection
     * and make sure it has the correct structure in our payload.
     *
     * @test
     * @group promotions
     *
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidPayloadException
     * @throws \Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException
     * @throws \Shopware\Core\Checkout\Promotion\Exception\UnknownPromotionDiscountTypeException
     */
    public function testPayloadWithSetGroup(): void
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('P123');
        $discount->setType(PromotionDiscountEntity::TYPE_PERCENTAGE);
        $discount->setValue(0);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $rule = new RuleEntity();
        $rule->setId('R1');
        $rule->setPayload($this->getFakeRule(10, '='));

        $ruleCollection = new RuleCollection([$rule]);

        $group = new PromotionSetGroupEntity();
        $group->setId(Uuid::randomBytes());
        $group->setPackagerKey('COUNT');
        $group->setValue(2);
        $group->setSorterKey('PRICE_ASC');
        $group->setSetGroupRules($ruleCollection);

        $this->promotion->setSetgroups(new PromotionSetGroupCollection([$group]));

        $builder = new PromotionItemBuilder();

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, Defaults::CURRENCY);

        $expected = [
            'promotionId' => 'PR-1',
            'discountType' => 'percentage',
            'value' => '0',
            'maxValue' => '',
            'discountId' => 'P123',
            'code' => '',
            'discountScope' => 'cart',
            'setGroups' => [
                [
                    'groupId' => $group->getId(),
                    'packagerKey' => 'COUNT',
                    'value' => 2.0,
                    'sorterKey' => 'PRICE_ASC',
                    'rules' => $ruleCollection,
                ],
            ],
            'groupId' => '',
            'filter' => [
                'sorterKey' => null,
                'applierKey' => null,
                'usageKey' => null,
            ],
            'exclusions' => [],
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
    public function testPayloadPercentageMaxValueWithAdvancedPrices(): void
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

        $builder = new PromotionItemBuilder();

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, $currency->getId());

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
    public function testPayloadAbsoluteMaxValueIsNull(): void
    {
        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(40);
        $discount->setMaxValue(30.0);
        $discount->setScope(PromotionDiscountEntity::SCOPE_CART);

        $builder = new PromotionItemBuilder();

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, Defaults::CURRENCY);

        static::assertEquals('', $item->getPayload()['maxValue']);
    }

    /**
     * This test verifies that the correct payload in the lineItem
     * by the discountItemBuilder
     *
     * @test
     * @group promotions
     */
    public function testPayloadDiscountValues(): void
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
        static::assertEquals($this->promotion->getId(), $item->getPayloadValue('promotionId'), 'Wrong value in payload key promotionId');
        static::assertEquals($discount->getId(), $item->getPayloadValue('discountId'), 'Wrong value in payload key discountId');
        static::assertEquals($discount->getType(), $item->getPayloadValue('discountType'), 'Wrong value in payload key discountType');
        static::assertEquals($discount->getScope(), $item->getPayloadValue('discountScope'), 'Wrong value in payload key scope');
    }

    /**
     * This test verifies that the correct filter
     * values are being added to the payload if set
     *
     * @test
     * @group promotions
     */
    public function testPayloadAdvancedFilterValues(): void
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setScope(PromotionDiscountEntity::SCOPE_DELIVERY);
        $discount->setConsiderAdvancedRules(true);
        $discount->setSorterKey('PRICE_ASC');
        $discount->setApplierKey('ALL');
        $discount->setUsageKey('UNLIMITED');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, Defaults::CURRENCY);

        static::assertEquals('PRICE_ASC', $item->getPayload()['filter']['sorterKey'], 'Wrong value in payload filter.sorterKey');
        static::assertEquals('ALL', $item->getPayload()['filter']['applierKey'], 'Wrong value in payload filter.applierKey');
        static::assertEquals('UNLIMITED', $item->getPayload()['filter']['usageKey'], 'Wrong value in payload filter.usageKey');
    }

    /**
     * This test verifies that the filter valures are all
     * null if the advanced rules option is disabled.
     * We enter valid values, but turn that feature off and
     * test if the values are null.
     *
     * @test
     * @group promotions
     */
    public function testPayloadAdvancedFilterValuesNullIfDisabled(): void
    {
        $builder = new PromotionItemBuilder();

        $discount = new PromotionDiscountEntity();
        $discount->setId('D5');
        $discount->setScope(PromotionDiscountEntity::SCOPE_DELIVERY);
        $discount->setConsiderAdvancedRules(false);
        $discount->setSorterKey('PRICE_ASC');
        $discount->setApplierKey('ALL');
        $discount->setUsageKey('UNLIMITED');
        $discount->setType(PromotionDiscountEntity::TYPE_ABSOLUTE);
        $discount->setValue(50);

        $item = $builder->buildDiscountLineItem('', $this->promotion, $discount, 1, Defaults::CURRENCY);

        static::assertNull($item->getPayload()['filter']['sorterKey'], 'Wrong value in payload filter.sorterKey');
        static::assertNull($item->getPayload()['filter']['applierKey'], 'Wrong value in payload filter.applierKey');
        static::assertNull($item->getPayload()['filter']['usageKey'], 'Wrong value in payload filter.usageKey');
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

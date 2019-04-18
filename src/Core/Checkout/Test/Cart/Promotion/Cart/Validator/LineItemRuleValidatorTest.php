<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Cart\Validator;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\Exception\MixedLineItemTypeException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\LineItemWithQuantityRule;
use Shopware\Core\Checkout\Promotion\Cart\Validator\LineItemRuleValidator;
use Shopware\Core\Checkout\Promotion\PromotionEntity;
use Shopware\Core\Content\Product\Cart\ProductCollector;
use Shopware\Core\Content\Rule\RuleCollection;
use Shopware\Core\Content\Rule\RuleEntity;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemRuleValidatorTest extends TestCase
{
    /**
     * @var LineItemRuleValidator
     */
    private $validator = null;

    /**
     * @var Cart
     */
    private $cart = null;

    /**
     * @var PromotionEntity
     */
    private $promotion = null;

    /**
     * @var MockObject
     */
    private $checkoutContext = null;

    /**
     * @throws \ReflectionException
     * @throws InvalidQuantityException
     * @throws MixedLineItemTypeException
     */
    public function setUp(): void
    {
        $rulePersona = new RuleEntity();
        $rulePersona->setId('PERSONA-1');

        $ruleScope = new RuleEntity();
        $ruleScope->setId('SCOPE-1');

        $this->promotion = new PromotionEntity();
        $this->promotion->setPersonaRules(new RuleCollection([$rulePersona]));
        $this->promotion->setScopeRule($ruleScope);

        $this->cart = new Cart('C1', 'TOKEN-1');
        $this->cart->add(new LineItem('P1', ProductCollector::LINE_ITEM_TYPE, 1, LineItem::GOODS_PRIORITY));
        $this->cart->add(new LineItem('P2', ProductCollector::LINE_ITEM_TYPE, 1, LineItem::GOODS_PRIORITY));

        $this->checkoutContext = $this->getMockBuilder(SalesChannelContext::class)->disableOriginalConstructor()->getMock();

        $this->validator = new LineItemRuleValidator('PROMOTION');
    }

    /**
     * This test verifies that we get all product items of the
     * cart, if no discount rule is even set.
     * So this doesn't restrict anything, and thus all items should be discounted.
     *
     * @test
     * @group promotions
     */
    public function testPromotionWithoutDiscountRule()
    {
        // make sure our discount rule is empty
        $this->promotion->assign(['discountRule' => null]);

        $itemIDs = $this->validator->getEligibleItemIds($this->promotion, $this->cart, $this->checkoutContext);

        $expectedItemIDs = [
            'P1',
            'P2',
        ];

        static::assertEquals($expectedItemIDs, $itemIDs);
    }

    /**
     * This test verifies, that we get an empty list if
     * no line items exist in the cart.
     *
     * @test
     * @group promotions
     */
    public function testPromotionWithoutProductItems()
    {
        // clear previous line items
        $this->cart->setLineItems(new LineItemCollection());

        $itemIDs = $this->validator->getEligibleItemIds($this->promotion, $this->cart, $this->checkoutContext);

        static::assertEquals([], $itemIDs);
    }

    /**
     * This test verifies, that we only get the P1 item in our list.
     * This is because we simulate a discount rule that consists
     * of a condition that only allows P1, also starting with a quantity of 5.
     * So we add P1 with quantity 5 and make sure only this one is in our list.
     * The other items must not be returned.
     *
     * @test
     * @group promotions
     */
    public function testDiscountRuleAffectsLineItemIDs()
    {
        // build our new rule, that only allows
        // P1 with quantity >= 5
        $itemRule = new LineItemWithQuantityRule();
        $itemRule->assign(['id' => 'P1', 'quantity' => 5, 'operator' => Rule::OPERATOR_GTE]);

        // add our rule condition to the rule entity
        // and set it in our promotion
        $discountRule = new RuleEntity();
        $discountRule->setId('P1-MIN-QUANTITY');
        $discountRule->setPayload($itemRule);
        $this->promotion->setDiscountRule($discountRule);

        // build a new cart with more items.
        // P1 will get a quantity of 5
        $this->cart = new Cart('C1', 'TOKEN-1');
        $this->cart->add(new LineItem('P1', ProductCollector::LINE_ITEM_TYPE, 5, LineItem::GOODS_PRIORITY));
        $this->cart->add(new LineItem('P2', ProductCollector::LINE_ITEM_TYPE, 1, LineItem::GOODS_PRIORITY));
        $this->cart->add(new LineItem('P3', ProductCollector::LINE_ITEM_TYPE, 1, LineItem::GOODS_PRIORITY));

        $itemIDs = $this->validator->getEligibleItemIds($this->promotion, $this->cart, $this->checkoutContext);

        $expectedItemIDs = [
            'P1',
        ];

        static::assertEquals($expectedItemIDs, $itemIDs);
    }
}

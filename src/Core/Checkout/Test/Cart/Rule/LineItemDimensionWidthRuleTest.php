<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWidthRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @group rules
 */
class LineItemDimensionWidthRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemDimensionWidthRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemDimensionWidthRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemDimensionWidth', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('amount', $ruleConstraints, 'Rule Constraint amount is not defined');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItem(
        string $operator,
        float $amount,
        ?float $lineItemAmount,
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithWidth($lineItemAmount),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function getMatchingRuleTestData(): array
    {
        return [
            // OPERATOR_EQ
            'match / operator equals / same width' => [Rule::OPERATOR_EQ, 100, 100, true],
            'no match / operator equals / different width' => [Rule::OPERATOR_EQ, 200, 100, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same width' => [Rule::OPERATOR_NEQ, 100, 100, false],
            'match / operator not equals / different width' => [Rule::OPERATOR_NEQ, 200, 100, true],
            // OPERATOR_GT
            'no match / operator greater than / lower width' => [Rule::OPERATOR_GT, 100, 50, false],
            'no match / operator greater than / same width' => [Rule::OPERATOR_GT, 100, 100, false],
            'match / operator greater than / higher width' => [Rule::OPERATOR_GT, 100, 200, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower width' => [Rule::OPERATOR_GTE, 100, 50, false],
            'match / operator greater than equals / same width' => [Rule::OPERATOR_GTE, 100, 100, true],
            'match / operator greater than equals / higher width' => [Rule::OPERATOR_GTE, 100, 200, true],
            // OPERATOR_LT
            'match / operator lower than / lower width' => [Rule::OPERATOR_LT, 100, 50, true],
            'no match / operator lower  than / same width' => [Rule::OPERATOR_LT, 100, 100, false],
            'no match / operator lower than / higher width' => [Rule::OPERATOR_LT, 100, 200, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower width' => [Rule::OPERATOR_LTE, 100, 50, true],
            'match / operator lower than equals / same width' => [Rule::OPERATOR_LTE, 100, 100, true],
            'no match / operator lower than equals / higher width' => [Rule::OPERATOR_LTE, 100, 200, false],
            // OPERATOR_EMPTY
            'match / operator empty / null width' => [Rule::OPERATOR_EMPTY, 100, null, true],
            'no match / operator empty / width' => [Rule::OPERATOR_EMPTY, 100, 200, false],
        ];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        float $amount,
        ?float $lineItemAmount1,
        ?float $lineItemAmount2,
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithWidth($lineItemAmount1),
            $this->createLineItemWithWidth($lineItemAmount2),
        ]);

        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScopeNested(
        string $operator,
        float $amount,
        ?float $lineItemAmount1,
        ?float $lineItemAmount2,
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $amount,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithWidth($lineItemAmount1),
            $this->createLineItemWithWidth($lineItemAmount2),
        ]);

        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function getCartRuleScopeTestData(): array
    {
        return [
            // OPERATOR_EQ
            'match / operator equals / same width' => [Rule::OPERATOR_EQ, 100, 100, 200, true],
            'no match / operator equals / different width' => [Rule::OPERATOR_EQ, 200, 100, 300, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same width' => [Rule::OPERATOR_NEQ, 100, 100, 100, false],
            'match / operator not equals / different width' => [Rule::OPERATOR_NEQ, 200, 100, 200, true],
            'match / operator not equals / different width 2' => [Rule::OPERATOR_NEQ, 200, 100, 300, true],
            // OPERATOR_GT
            'no match / operator greater than / lower width' => [Rule::OPERATOR_GT, 100, 50, 70, false],
            'no match / operator greater than / same width' => [Rule::OPERATOR_GT, 100, 100, 70, false],
            'match / operator greater than / higher width' => [Rule::OPERATOR_GT, 100, 200, 70, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower width' => [Rule::OPERATOR_GTE, 100, 50, 70, false],
            'match / operator greater than equals / same width' => [Rule::OPERATOR_GTE, 100, 100, 70, true],
            'match / operator greater than equals / higher width' => [Rule::OPERATOR_GTE, 100, 200, 70, true],
            // OPERATOR_LT
            'match / operator lower than / lower width' => [Rule::OPERATOR_LT, 100, 50, 120, true],
            'no match / operator lower  than / same width' => [Rule::OPERATOR_LT, 100, 100, 120, false],
            'no match / operator lower than / higher width' => [Rule::OPERATOR_LT, 100, 200, 120, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower width' => [Rule::OPERATOR_LTE, 100, 50, 120, true],
            'match / operator lower than equals / same width' => [Rule::OPERATOR_LTE, 100, 100, 120, true],
            'no match / operator lower than equals / higher width' => [Rule::OPERATOR_LTE, 100, 200, 120, false],
            // OPERATOR_EMPTY
            'match / operator empty / lower width' => [Rule::OPERATOR_EMPTY, 100, null, 120, true],
            'match / operator empty / same width' => [Rule::OPERATOR_EMPTY, 100, 100, null, true],
            'no match / operator empty / higher width' => [Rule::OPERATOR_EMPTY, 100, 200, 120, false],
        ];
    }

    /**
     * @throws InvalidQuantityException
     */
    public function testMatchWithEmptyDimensionWidthPayload(): void
    {
        $this->rule->assign(['amount' => 100, 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItem(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testConstraints(): void
    {
        $expectedOperators = [
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_GTE,
            Rule::OPERATOR_LTE,
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_GT,
            Rule::OPERATOR_LT,
            Rule::OPERATOR_EMPTY,
        ];

        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('operator', $ruleConstraints, 'Constraint operator not found in Rule');
        $operators = $ruleConstraints['operator'];
        static::assertEquals(new NotBlank(), $operators[0]);
        static::assertEquals(new Choice($expectedOperators), $operators[1]);

        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);
        static::assertArrayHasKey('amount', $ruleConstraints, 'Constraint amount not found in Rule');
        $amount = $ruleConstraints['amount'];
        static::assertEquals(new NotBlank(), $amount[0]);
        static::assertEquals(new Type('numeric'), $amount[1]);
    }

    private function createLineItemWithWidth(?float $width): LineItem
    {
        return $this->createLineItemWithDeliveryInfo(false, 1, 50.0, null, $width);
    }
}

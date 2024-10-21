<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemGoodsTotalRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Rule\Container\OrRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\Rule\FalseRule;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(LineItemGoodsTotalRule::class)]
#[Group('rules')]
class LineItemGoodsTotalRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    public function testMatchWithLineItemScopeAndLineItemIsNotGood(): void
    {
        $rule = new LineItemGoodsTotalRule(Rule::OPERATOR_EQ, 1);

        $lineItem = $this->createLineItem()->setGood(false);

        $match = $rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testMatchWithLineItemScopeWithNotMatchFilter(): void
    {
        $rule = new LineItemGoodsTotalRule(Rule::OPERATOR_EQ, 6);
        $rule->addRule(new FalseRule());

        $lineItem = $this->createLineItemWithGoodsCount();
        $lineItem->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection(), 6));

        $match = $rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    #[DataProvider('matchWithoutFilterTestDataProvider')]
    public function testMatchWithLineItemScopeWithoutFilter(string $operator, int $count, bool $expected): void
    {
        $rule = new LineItemGoodsTotalRule($operator, $count);

        $lineItem = $this->createLineItemWithGoodsCount();
        $lineItem->setPriceDefinition(new QuantityPriceDefinition(100, new TaxRuleCollection(), 6));

        $match = $rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    #[DataProvider('matchWithoutFilterTestDataProvider')]
    public function testMatchWithoutFilter(string $operator, int $count, bool $expectedResult): void
    {
        $lineItemCollection = new LineItemCollection([
            $this->createLineItem('foo', 4),
            $this->createLineItem('bar', 2),
        ]);

        $cart = new Cart('test-token');
        $cart->addLineItems($lineItemCollection);

        $scope = new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        );

        $lineItemGoodsTotalRule = new LineItemGoodsTotalRule($operator, $count);

        static::assertSame($expectedResult, $lineItemGoodsTotalRule->match($scope));
    }

    /**
     * @return \Generator<array<int, string|int|bool>>
     */
    public static function matchWithoutFilterTestDataProvider(): \Generator
    {
        yield 'OPERATOR_EQ with count 6 expect true' => [Rule::OPERATOR_EQ, 6, true];
        yield 'OPERATOR_EQ with count 5 expect false' => [Rule::OPERATOR_EQ, 5, false];
        yield 'OPERATOR_EQ with count 7 expect false' => [Rule::OPERATOR_EQ, 7, false];

        yield 'OPERATOR_LTE with count 5 expect false' => [Rule::OPERATOR_LTE, 5, false];
        yield 'OPERATOR_LTE with count 6 expect true' => [Rule::OPERATOR_LTE, 6, true];
        yield 'OPERATOR_LTE with count 7 expect true' => [Rule::OPERATOR_LTE, 7, true];

        yield 'OPERATOR_GTE with count 5 expect true' => [Rule::OPERATOR_GTE, 5, true];
        yield 'OPERATOR_GTE with count 6 expect true' => [Rule::OPERATOR_GTE, 6, true];
        yield 'OPERATOR_GTE with count 7 expect false' => [Rule::OPERATOR_GTE, 7, false];

        yield 'OPERATOR_NEQ with count 5 expect true' => [Rule::OPERATOR_NEQ, 5, true];
        yield 'OPERATOR_NEQ with count 7 expect true' => [Rule::OPERATOR_NEQ, 7, true];
        yield 'OPERATOR_NEQ with count 6 expect false' => [Rule::OPERATOR_NEQ, 6, false];

        yield 'OPERATOR_GT with count 5 expect true' => [Rule::OPERATOR_GT, 5, true];
        yield 'OPERATOR_GT with count 6 expect false' => [Rule::OPERATOR_GT, 6, false];
        yield 'OPERATOR_GT with count 7 expect false' => [Rule::OPERATOR_GT, 7, false];

        yield 'OPERATOR_LT with count 5 expect false' => [Rule::OPERATOR_LT, 5, false];
        yield 'OPERATOR_LT with count 6 expect false' => [Rule::OPERATOR_LT, 6, false];
        yield 'OPERATOR_LT with count 7 expect true' => [Rule::OPERATOR_LT, 7, true];
    }

    #[DataProvider('matchWithFilterTestDataProvider')]
    public function testMatchWithFilter(string $operator, int $count, bool $expectedResult): void
    {
        $lineItemCollection = new LineItemCollection([
            $this->createLineItem('foo', 4),
            $this->createLineItem('bar', 2),
        ]);

        $cart = new Cart('test-token');
        $cart->addLineItems($lineItemCollection);

        $scope = new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        );

        $lineItemGoodsTotalRule = new LineItemGoodsTotalRule($operator, $count);
        $lineItemGoodsTotalRule->addRule(new OrRule());

        static::assertSame($expectedResult, $lineItemGoodsTotalRule->match($scope));
    }

    /**
     * @return \Generator<array<int, string|int|bool>>
     */
    public static function matchWithFilterTestDataProvider(): \Generator
    {
        yield 'OPERATOR_EQ with count 5 expect false' => [Rule::OPERATOR_EQ, 5, false];
        yield 'OPERATOR_EQ with count 6 expect false' => [Rule::OPERATOR_EQ, 6, false];
        yield 'OPERATOR_EQ with count 7 expect false' => [Rule::OPERATOR_EQ, 7, false];

        yield 'OPERATOR_LTE with count 5 expect true' => [Rule::OPERATOR_LTE, 5, true];
        yield 'OPERATOR_LTE with count 6 expect true' => [Rule::OPERATOR_LTE, 6, true];
        yield 'OPERATOR_LTE with count 7 expect true' => [Rule::OPERATOR_LTE, 7, true];

        yield 'OPERATOR_GTE with count 5 expect false' => [Rule::OPERATOR_GTE, 5, false];
        yield 'OPERATOR_GTE with count 6 expect false' => [Rule::OPERATOR_GTE, 6, false];
        yield 'OPERATOR_GTE with count 7 expect false' => [Rule::OPERATOR_GTE, 7, false];

        yield 'OPERATOR_NEQ with count 5 expect true' => [Rule::OPERATOR_NEQ, 5, true];
        yield 'OPERATOR_NEQ with count 6 expect true' => [Rule::OPERATOR_NEQ, 6, true];
        yield 'OPERATOR_NEQ with count 7 expect true' => [Rule::OPERATOR_NEQ, 7, true];

        yield 'OPERATOR_GT with count 5 expect false' => [Rule::OPERATOR_GT, 5, false];
        yield 'OPERATOR_GT with count 6 expect false' => [Rule::OPERATOR_GT, 6, false];
        yield 'OPERATOR_GT with count 7 expect false' => [Rule::OPERATOR_GT, 7, false];

        yield 'OPERATOR_LT with count 5 expect true' => [Rule::OPERATOR_LT, 5, true];
        yield 'OPERATOR_LT with count 6 expect true' => [Rule::OPERATOR_LT, 6, true];
        yield 'OPERATOR_LT with count 7 expect true' => [Rule::OPERATOR_LT, 7, true];
    }

    public function testGetConstraints(): void
    {
        $lineItemGoodsTotalRule = new LineItemGoodsTotalRule();

        $result = $lineItemGoodsTotalRule->getConstraints();

        static::assertInstanceOf(NotBlank::class, $result['count'][0]);
        static::assertInstanceOf(Type::class, $result['count'][1]);

        $expected = RuleConstraints::numericOperators(false)[1];
        static::assertInstanceOf(Choice::class, $expected);

        $operatorResult = $result['operator'][1];
        static::assertInstanceOf(Choice::class, $operatorResult);

        static::assertSame($expected->choices, $operatorResult->choices);
    }

    private function createLineItemWithGoodsCount(): LineItem
    {
        return $this->createLineItem()->setGood(true);
    }
}

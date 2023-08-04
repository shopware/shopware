<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemVariantValueRule;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package business-ops
 *
 * @internal
 *
 * @group rules
 *
 * @covers \Shopware\Core\Checkout\Cart\Rule\LineItemVariantValueRule
 */
class LineItemVariantValueRuleTest extends TestCase
{
    private LineItemVariantValueRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemVariantValueRule();
    }

    public function testName(): void
    {
        static::assertSame('cartLineItemVariantValue', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('identifiers', $constraints, 'identifiers constraint not found');
        static::assertArrayHasKey('operator', $constraints, 'operator constraint not found');

        static::assertEquals(RuleConstraints::uuids(), $constraints['identifiers']);
        static::assertEquals(RuleConstraints::uuidOperators(false), $constraints['operator']);
    }

    /**
     * @dataProvider getMatchValues
     *
     * @param list<string> $identifiers
     * @param list<string> $itemOptionIds
     */
    public function testCartScopeMatching(bool $expected, array $itemOptionIds, array $identifiers, string $operator): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, null, 1);
        $lineItem->setPayloadValue('optionIds', $itemOptionIds);
        $lineItems = new LineItemCollection([$lineItem]);

        $cart = $this->createMock(Cart::class);
        $cart->method('getLineItems')->willReturn($lineItems);

        $context = $this->createMock(SalesChannelContext::class);
        $scope = new CartRuleScope($cart, $context);

        $this->rule->assign(['identifiers' => $identifiers, 'operator' => $operator]);

        static::assertSame(
            $expected,
            $this->rule->match($scope)
        );
    }

    /**
     * @dataProvider getMatchValues
     *
     * @param list<string> $identifiers
     * @param list<string> $itemOptionIds
     */
    public function testLineItemScopeMatching(bool $expected, array $itemOptionIds, array $identifiers, string $operator): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, null, 1);
        $lineItem->setPayloadValue('optionIds', $itemOptionIds);

        $context = $this->createMock(SalesChannelContext::class);
        $scope = new LineItemScope($lineItem, $context);

        $this->rule->assign(['identifiers' => $identifiers, 'operator' => $operator]);

        static::assertSame(
            $expected,
            $this->rule->match($scope)
        );
    }

    public function testInvalidScopeIsFalse(): void
    {
        $invalidScope = $this->createMock(RuleScope::class);
        $this->rule->assign(['identifiers' => [uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
        static::assertFalse($this->rule->match($invalidScope));
    }

    public function testEmptyPayloadValue(): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, null, 1);

        $context = $this->createMock(SalesChannelContext::class);
        $scope = new LineItemScope($lineItem, $context);

        $this->rule->assign(['identifiers' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse(
            $this->rule->match($scope)
        );
    }

    public function testConfig(): void
    {
        $config = (new LineItemVariantValueRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;

        static::assertEquals([
            'operators' => $operators,
            'isMatchAny' => true,
        ], $configData['operatorSet']);
    }

    /**
     * @return array<string, array{boolean, list<string>, list<string>, string}>
     */
    public static function getMatchValues(): iterable
    {
        $id = Uuid::randomHex();

        return [
            yield 'should match when option id is included' => [true, [$id], [$id, Uuid::randomHex()], Rule::OPERATOR_EQ],
            yield 'should not match when option id is not included' => [false, [$id], [Uuid::randomHex()], Rule::OPERATOR_EQ],
            yield 'should match when option id is not included' => [true, [$id, Uuid::randomHex()], [Uuid::randomHex()], Rule::OPERATOR_NEQ],
            yield 'should not match when option id is included' => [false, [$id, Uuid::randomHex()], [$id], Rule::OPERATOR_NEQ],
        ];
    }
}

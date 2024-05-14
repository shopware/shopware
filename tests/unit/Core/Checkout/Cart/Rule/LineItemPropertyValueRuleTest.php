<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemPropertyValueRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @package services-settings
 *
 * @internal
 */
#[CoversClass(LineItemPropertyValueRule::class)]
#[Group('rules')]
class LineItemPropertyValueRuleTest extends TestCase
{
    private LineItemPropertyValueRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemPropertyValueRule();
    }

    public function testName(): void
    {
        static::assertSame('cartLineItemPropertyValue', $this->rule->getName());
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
     * @param list<string> $identifiers
     * @param list<string> $itemPropertyIds
     */
    #[DataProvider('getMatchValues')]
    public function testCartScopeMatching(bool $expected, array $itemPropertyIds, array $identifiers, string $operator): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, null, 1);
        $lineItem->setPayloadValue('propertyIds', $itemPropertyIds);
        $lineItems = new LineItemCollection([$lineItem]);

        $cart = new Cart(Uuid::randomHex());
        $cart->setLineItems($lineItems);

        $context = $this->createMock(SalesChannelContext::class);
        $scope = new CartRuleScope($cart, $context);

        $this->rule->assign(['identifiers' => $identifiers, 'operator' => $operator]);

        static::assertSame(
            $expected,
            $this->rule->match($scope)
        );
    }

    /**
     * @param list<string> $identifiers
     * @param list<string> $itemPropertyIds
     */
    #[DataProvider('getMatchValues')]
    public function testLineItemScopeMatching(bool $expected, array $itemPropertyIds, array $identifiers, string $operator): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE, null, 1);
        $lineItem->setPayloadValue('propertyIds', $itemPropertyIds);

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
        $invalidScope = new CheckoutRuleScope($this->createMock(SalesChannelContext::class));
        $this->rule->assign(['identifiers' => [Uuid::randomHex()], 'operator' => Rule::OPERATOR_EQ]);
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
        $config = (new LineItemPropertyValueRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        $operators = RuleConfig::OPERATOR_SET_STRING;

        static::assertEquals([
            'operators' => $operators,
            'isMatchAny' => true,
        ], $configData['operatorSet']);
    }

    /**
     * @return array<string, array{bool, list<string>, list<string>, string}>
     */
    public static function getMatchValues(): iterable
    {
        $id = Uuid::randomHex();

        return [
            yield 'should match when property id is included' => [true, [$id], [$id, Uuid::randomHex()], Rule::OPERATOR_EQ],
            yield 'should not match when property id is not included' => [false, [$id], [Uuid::randomHex()], Rule::OPERATOR_EQ],
            yield 'should match when property id is not included' => [true, [$id, Uuid::randomHex()], [Uuid::randomHex()], Rule::OPERATOR_NEQ],
            yield 'should not match when property id is included' => [false, [$id, Uuid::randomHex()], [$id], Rule::OPERATOR_NEQ],
        ];
    }
}

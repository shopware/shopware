<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemPerItemQuantityRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemPerItemQuantityRule::class)]
#[Group('rules')]
class LineItemPerItemQuantityRuleTest extends TestCase
{
    private LineItemPerItemQuantityRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemPerItemQuantityRule();
    }

    public function testName(): void
    {
        static::assertSame('cartLineItemPerItemQuantity', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('quantity', $constraints);
        static::assertArrayHasKey('operator', $constraints);

        static::assertEquals(RuleConstraints::int(), $constraints['quantity']);
        static::assertEquals(RuleConstraints::numericOperators(false), $constraints['operator']);
    }

    public function testConfig(): void
    {
        $configData = $this->rule->getConfig()->getData();

        static::assertSame([
            'operators' => RuleConfig::OPERATOR_SET_NUMBER,
            'isMatchAny' => true,
        ], $configData['operatorSet']);

        static::assertArrayHasKey('quantity', $configData['fields']);
        static::assertSame('int', $configData['fields']['quantity']['type']);
    }

    #[DataProvider('getMatchingValues')]
    public function testMatchingCartScope(bool $expected, int $cartQuantity, int $quantityValue, string $operator): void
    {
        $cart = new Cart('test');
        $cart->add(new LineItem(Uuid::randomHex(), 'product', null, $cartQuantity));

        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));
        $this->rule->assign(['quantity' => $quantityValue, 'operator' => $operator]);

        static::assertSame($expected, $this->rule->match($scope));
    }

    #[DataProvider('getMatchingValues')]
    public function testMatchingLineItemScope(bool $expected, int $cartQuantity, int $quantityValue, string $operator): void
    {
        $lineItem = new LineItem(Uuid::randomHex(), 'product', null, $cartQuantity);

        $scope = new LineItemScope($lineItem, $this->createMock(SalesChannelContext::class));
        $this->rule->assign(['quantity' => $quantityValue, 'operator' => $operator]);

        static::assertSame($expected, $this->rule->match($scope));
    }

    public function testAtLeastOneMatchingItemMatches(): void
    {
        $cart = new Cart('test');
        $cart->add(new LineItem(Uuid::randomHex(), 'product', null, 1));
        $cart->add(new LineItem(Uuid::randomHex(), 'product', null, 5));

        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));
        $this->rule->assign(['quantity' => 5, 'operator' => Rule::OPERATOR_EQ]);

        static::assertTrue($this->rule->match($scope));
    }

    public function testEmptyCartIsFalse(): void
    {
        $scope = new CartRuleScope(new Cart('test'), $this->createMock(SalesChannelContext::class));
        $this->rule->assign(['quantity' => 5, 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($this->rule->match($scope));
    }

    public function testUnsetQuantityIsFalse(): void
    {
        $scope = new LineItemScope(
            new LineItem(Uuid::randomHex(), 'product', null, 1),
            $this->createMock(SalesChannelContext::class)
        );
        $this->rule->assign(['operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($this->rule->match($scope));
    }

    public function testInvalidScopeIsFalse(): void
    {
        $this->rule->assign(['quantity' => 5, 'operator' => Rule::OPERATOR_EQ]);

        static::assertFalse($this->rule->match($this->createMock(RuleScope::class)));
    }

    /**
     * @return array<string, array{bool, int, int, string}>
     */
    public static function getMatchingValues(): array
    {
        return [
            'EQ - true' => [true, 5, 5, Rule::OPERATOR_EQ],
            'EQ - false' => [false, 4, 5, Rule::OPERATOR_EQ],
            'NEQ - true' => [true, 4, 5, Rule::OPERATOR_NEQ],
            'NEQ - false' => [false, 5, 5, Rule::OPERATOR_NEQ],
            'GT - true' => [true, 6, 5, Rule::OPERATOR_GT],
            'GT - false' => [false, 4, 5, Rule::OPERATOR_GT],
            'GTE - trueEQ' => [true, 5, 5, Rule::OPERATOR_GTE],
            'GTE - trueGreater' => [true, 6, 5, Rule::OPERATOR_GTE],
            'GTE - false' => [false, 4, 5, Rule::OPERATOR_GTE],
            'LT - true' => [true, 4, 5, Rule::OPERATOR_LT],
            'LT - false' => [false, 6, 5, Rule::OPERATOR_LT],
            'LTE - trueEQ' => [true, 5, 5, Rule::OPERATOR_LTE],
            'LTE - trueLower' => [true, 4, 5, Rule::OPERATOR_LTE],
            'LTE - false' => [false, 6, 5, Rule::OPERATOR_LTE],
        ];
    }
}

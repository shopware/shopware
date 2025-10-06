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
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Customer\Rule\TestRuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;

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

    public function testGetName(): void
    {
        static::assertSame(
            'cartLineItemPerItemQuantity',
            (new LineItemPerItemQuantityRule())->getName()
        );
    }

    public function testConstraints(): void
    {
        $operators = [
            Rule::OPERATOR_EQ,
            Rule::OPERATOR_LTE,
            Rule::OPERATOR_GTE,
            Rule::OPERATOR_NEQ,
            Rule::OPERATOR_GT,
            Rule::OPERATOR_LT,
        ];

        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('quantity', $constraints);
        static::assertArrayHasKey('operator', $constraints);

        static::assertEquals(new Type(type: 'int'), $constraints['quantity'][1]);
        static::assertEquals(new Choice($operators), $constraints['operator'][1]);
    }

    #[DataProvider('getMatchingValues')]
    public function testLineItemPerQuantityRuleMatchingCartScope(bool $expected, int $cartQuantity, int $quantityValue, string $operator): void
    {
        $cart = new Cart('test');
        $cart->add(new LineItem('test1', 'product', null, $cartQuantity));

        $scope = new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class),
        );
        $this->rule->assign([
            'quantity' => $quantityValue,
            'operator' => $operator,
        ]);

        $isMatching = $this->rule->match($scope);

        static::assertSame($expected, $isMatching);
    }

    #[DataProvider('getMatchingValues')]
    public function testLineItemPerQuantityRuleMatchingLineItemScope(bool $expected, int $cartQuantity, int $quantityValue, string $operator): void
    {
        $lineItem = new LineItem('test1', 'product', null, $cartQuantity);

        $scope = new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class),
        );
        $this->rule->assign([
            'quantity' => $quantityValue,
            'operator' => $operator,
        ]);

        $isMatching = $this->rule->match($scope);

        static::assertSame($expected, $isMatching);
    }

    public function testEmptyCartShouldReturnFalse(): void
    {
        $cart = new Cart('test');

        $scope = new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class),
        );
        $this->rule->assign([
            'quantity' => 5,
            'operator' => Rule::OPERATOR_EQ,
        ]);

        $isMatching = $this->rule->match($scope);

        static::assertFalse($isMatching);
    }

    public function testUnhandledScopeShouldReturnFalse(): void
    {
        $scope = new TestRuleScope(
            $this->createMock(SalesChannelContext::class),
        );

        $this->rule->assign([
            'quantity' => 5,
            'operator' => Rule::OPERATOR_EQ,
        ]);

        $isMatching = $this->rule->match($scope);

        static::assertFalse($isMatching);
    }

    public function testUnsetQuantityShouldReturnFalse(): void
    {
        $scope = new LineItemScope(
            new LineItem('test1', 'product', null, 1),
            $this->createMock(SalesChannelContext::class),
        );

        $this->rule->assign([
            'operator' => Rule::OPERATOR_EQ,
        ]);

        $isMatching = $this->rule->match($scope);

        static::assertFalse($isMatching);
    }

    public static function getMatchingValues(): \Generator
    {
        yield 'EQ - true' => [
            true,
            5,
            5,
            Rule::OPERATOR_EQ,
        ];

        yield 'EQ - false' => [
            false,
            4,
            5,
            Rule::OPERATOR_EQ,
        ];

        yield 'NEQ - true' => [
            true,
            4,
            5,
            Rule::OPERATOR_NEQ,
        ];

        yield 'NEQ - false' => [
            false,
            5,
            5,
            Rule::OPERATOR_NEQ,
        ];

        yield 'GT - true' => [
            true,
            6,
            5,
            Rule::OPERATOR_GT,
        ];

        yield 'GT - false' => [
            false,
            4,
            5,
            Rule::OPERATOR_GT,
        ];

        yield 'GTE - true' => [
            true,
            5,
            5,
            Rule::OPERATOR_GTE,
        ];

        yield 'GTE - false' => [
            false,
            4,
            5,
            Rule::OPERATOR_GTE,
        ];

        yield 'GTE - true greater' => [
            true,
            6,
            5,
            Rule::OPERATOR_GTE,
        ];

        yield 'LT - true' => [
            true,
            4,
            5,
            Rule::OPERATOR_LT,
        ];

        yield 'LT - false' => [
            false,
            6,
            5,
            Rule::OPERATOR_LT,
        ];

        yield 'LTE - true' => [
            true,
            5,
            5,
            Rule::OPERATOR_LTE,
        ];

        yield 'LTE - false' => [
            false,
            6,
            5,
            Rule::OPERATOR_LTE,
        ];

        yield 'LTE - true lower' => [
            true,
            4,
            5,
            Rule::OPERATOR_LTE,
        ];
    }
}

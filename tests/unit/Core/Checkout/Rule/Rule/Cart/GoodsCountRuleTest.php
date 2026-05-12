<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\GoodsCountRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Rule\SimpleRule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(GoodsCountRule::class)]
class GoodsCountRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    public function testRuleWithExactCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithExactCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_EQ]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualExactCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 1, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 2, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_LTE]);

        $cart = new Cart('test');

        $cart->add((new LineItem('A', 'test'))->setGood(true));

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualExactCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 1, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 0, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 2, 'operator' => Rule::OPERATOR_GTE]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualCountMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 2, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test');
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithNotEqualCountNotMatch(): void
    {
        $rule = (new GoodsCountRule())->assign(['count' => 1, 'operator' => Rule::OPERATOR_NEQ]);

        $cart = new Cart('test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));

        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testMatchWithWrongScopeShouldReturnFalse(): void
    {
        $goodsCountRule = new GoodsCountRule();
        $wrongScope = $this->createMock(RuleScope::class);

        static::assertFalse($goodsCountRule->match($wrongScope));
    }

    public function testMatchWithSimpleRule(): void
    {
        $goodsCountRule = new GoodsCountRule(Rule::OPERATOR_EQ, 3);
        $goodsCountRule->setRules([new SimpleRule()]);

        static::assertTrue($goodsCountRule->match($this->createCartRuleScope()));
    }

    public function testMatchWithSimpleRuleExpectFalse(): void
    {
        $goodsCountRule = new GoodsCountRule(Rule::OPERATOR_EQ, 3);
        $goodsCountRule->setRules([new SimpleRule(false)]);

        static::assertFalse($goodsCountRule->match($this->createCartRuleScope()));
    }

    public function testGetConstraints(): void
    {
        $goodsCountRule = new GoodsCountRule();

        $result = $goodsCountRule->getConstraints();

        static::assertEquals([
            'count' => RuleConstraints::int(),
            'operator' => RuleConstraints::numericOperators(false),
        ], $result);
    }

    public function testConstraintsRejectStringCount(): void
    {
        $violations = $this->validateConstraint('count', '3');

        $this->assertViolationCode($violations, Type::INVALID_TYPE_ERROR);
    }

    public function testConstraintsRejectFloatCount(): void
    {
        $violations = $this->validateConstraint('count', 1.1);

        $this->assertViolationCode($violations, Type::INVALID_TYPE_ERROR);
    }

    #[DataProvider('validNumericOperators')]
    public function testConstraintsAcceptAvailableOperators(string $operator): void
    {
        $violations = $this->validateConstraint('operator', $operator);

        static::assertCount(0, $violations);
    }

    #[DataProvider('invalidNumericOperators')]
    public function testConstraintsRejectInvalidOperator(string $operator): void
    {
        $violations = $this->validateConstraint('operator', $operator);

        $this->assertViolationCode($violations, Choice::NO_SUCH_CHOICE_ERROR);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function validNumericOperators(): \Generator
    {
        yield 'equals' => [Rule::OPERATOR_EQ];
        yield 'not equals' => [Rule::OPERATOR_NEQ];
        yield 'less than or equals' => [Rule::OPERATOR_LTE];
        yield 'greater than or equals' => [Rule::OPERATOR_GTE];
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function invalidNumericOperators(): \Generator
    {
        yield 'unknown operator' => ['Invalid'];
    }

    #[DataProvider('getLineItemScopeTestData')]
    public function testIfMatchesAllCorrectWithLineItemScope(
        int $count,
        string $operator,
        bool $expected
    ): void {
        $rule = (new GoodsCountRule())->assign(['count' => $count, 'operator' => $operator]);

        $match = $rule->match(new LineItemScope(
            $this->createLineItemWithGoodsCount(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, mixed[]>
     */
    public static function getLineItemScopeTestData(): array
    {
        return [
            'product / equal / match count' => [1, Rule::OPERATOR_EQ, true],
            'product / equal / no match count' => [0, Rule::OPERATOR_EQ, false],
            'product / lower than or equal / match count' => [2, Rule::OPERATOR_LTE, true],
            'product / lower than or equal / no match count' => [0, Rule::OPERATOR_LTE, false],
            'product / lower than or equal / match equal count' => [1, Rule::OPERATOR_LTE, true],
            'product / greater than or equal / match count' => [0, Rule::OPERATOR_GTE, true],
            'product / greater than or equal / no match count' => [2, Rule::OPERATOR_GTE, false],
            'product / greater than or equal / match equal count' => [1, Rule::OPERATOR_GTE, true],
            'product / not equal / match count' => [2, Rule::OPERATOR_NEQ, true],
            'product / not equal / no match count' => [1, Rule::OPERATOR_NEQ, false],
        ];
    }

    private function createCartRuleScope(): CartRuleScope
    {
        $cart = new Cart('test');
        $cart->add((new LineItem('a', 'a'))->setGood(true));
        $cart->add((new LineItem('b', 'a'))->setGood(true));
        $cart->add((new LineItem('c', 'a'))->setGood(true));

        $context = $this->createMock(SalesChannelContext::class);

        return new CartRuleScope($cart, $context);
    }

    private function createLineItemWithGoodsCount(): LineItem
    {
        return $this->createLineItem()->setGood(true);
    }

    private function validateConstraint(string $field, mixed $value): ConstraintViolationListInterface
    {
        return Validation::createValidator()->validate($value, (new GoodsCountRule())->getConstraints()[$field]);
    }

    private function assertViolationCode(ConstraintViolationListInterface $violations, string $expectedCode): void
    {
        static::assertCount(1, $violations);

        foreach ($violations as $violation) {
            static::assertSame($expectedCode, $violation->getCode());
        }
    }
}

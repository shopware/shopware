<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CartAmountRule;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleComparison;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CartAmountRule::class)]
class CartAmountRuleTest extends TestCase
{
    public function testRuleWithExactAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 275, 'operator' => CartAmountRule::OPERATOR_EQ]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithExactAmountNotMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 0, 'operator' => CartAmountRule::OPERATOR_EQ]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualExactAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 275, 'operator' => CartAmountRule::OPERATOR_LTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 300, 'operator' => CartAmountRule::OPERATOR_LTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithLowerThanEqualAmountNotMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 274, 'operator' => CartAmountRule::OPERATOR_LTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualExactAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 275, 'operator' => CartAmountRule::OPERATOR_GTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 100, 'operator' => CartAmountRule::OPERATOR_GTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleWithGreaterThanEqualAmountNotMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 276, 'operator' => CartAmountRule::OPERATOR_GTE]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleNotEqualAmountMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 0, 'operator' => CartAmountRule::OPERATOR_NEQ]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertTrue(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    public function testRuleNotEqualAmountNotMatch(): void
    {
        $rule = (new CartAmountRule())->assign(['amount' => 275, 'operator' => CartAmountRule::OPERATOR_NEQ]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    #[DataProvider('unsupportedOperators')]
    public function testUnsupportedOperators(string $operator): void
    {
        $this->expectExceptionObject(RuleException::unsupportedOperator($operator, RuleComparison::class));

        $rule = (new CartAmountRule())->assign(['amount' => 100, 'operator' => $operator]);

        $cart = Generator::createCart();
        $context = $this->createMock(SalesChannelContext::class);

        static::assertFalse(
            $rule->match(new CartRuleScope($cart, $context))
        );
    }

    /**
     * @return array<string[]>
     */
    public static function unsupportedOperators(): array
    {
        return [
            ['random'],
            [''],
        ];
    }

    public function testMatchShouldReturnFalseScopeIsNotCartRuleScope(): void
    {
        $ruleScope = new CheckoutRuleScope($this->createMock(SalesChannelContext::class));
        $cartAmountRule = new CartAmountRule();

        static::assertFalse($cartAmountRule->match($ruleScope));
    }

    public function testGetConstraints(): void
    {
        $result = (new CartAmountRule())->getConstraints();

        static::assertEquals([
            'amount' => RuleConstraints::float(),
            'operator' => RuleConstraints::numericOperators(false),
        ], $result);
    }

    public function testConstraintsRejectMissingAmount(): void
    {
        $violations = $this->validateConstraint('amount', null);

        $this->assertViolationCode($violations, NotBlank::IS_BLANK_ERROR);
    }

    public function testConstraintsAcceptNumericStringAmount(): void
    {
        $violations = $this->validateConstraint('amount', '0.1');

        static::assertCount(0, $violations);
    }

    public function testConstraintsAcceptIntegerAmount(): void
    {
        $violations = $this->validateConstraint('amount', 3);

        static::assertCount(0, $violations);
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
        yield 'equals' => [CartAmountRule::OPERATOR_EQ];
        yield 'not equals' => [CartAmountRule::OPERATOR_NEQ];
        yield 'less than or equals' => [CartAmountRule::OPERATOR_LTE];
        yield 'greater than or equals' => [CartAmountRule::OPERATOR_GTE];
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function invalidNumericOperators(): \Generator
    {
        yield 'unknown operator' => ['Invalid'];
    }

    public function testGetConfig(): void
    {
        $data = (new CartAmountRule())->getConfig()->getData();

        static::assertSame(RuleConfig::OPERATOR_SET_NUMBER, $data['operatorSet']['operators']);
        static::assertSame('amount', $data['fields']['amount']['name']);
    }

    private function validateConstraint(string $field, mixed $value): ConstraintViolationListInterface
    {
        return Validation::createValidator()->validate($value, (new CartAmountRule())->getConstraints()[$field]);
    }

    private function assertViolationCode(ConstraintViolationListInterface $violations, string $expectedCode): void
    {
        static::assertCount(1, $violations);

        foreach ($violations as $violation) {
            static::assertSame($expectedCode, $violation->getCode());
        }
    }
}

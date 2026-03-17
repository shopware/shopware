<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartTotalTaxRule;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(CartTotalTaxRule::class)]
#[Group('rules')]
class CartTotalTaxRuleTest extends TestCase
{
    public function testGetName(): void
    {
        static::assertSame('cartTotalTax', (new CartTotalTaxRule())->getName());
    }

    public function testMatchReturnsFalseForNonCartRuleScope(): void
    {
        $rule = new CartTotalTaxRule();

        static::assertFalse($rule->match($this->createMock(RuleScope::class)));
    }

    public function testMatchReturnsFalseForCheckoutRuleScope(): void
    {
        $rule = new CartTotalTaxRule();

        $scope = new CheckoutRuleScope($this->createMock(SalesChannelContext::class));
        static::assertFalse($rule->match($scope));
    }

    /**
     * @param float[] $taxAmounts
     */
    #[DataProvider('provideMatchTestCases')]
    public function testMatch(string $operator, array $taxAmounts, float $amount, bool $expected): void
    {
        $rule = new CartTotalTaxRule();
        $rule->assign(['operator' => $operator, 'amount' => $amount]);

        $scope = new CartRuleScope(
            $this->createCartWithTaxes($taxAmounts),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame($expected, $rule->match($scope));
    }

    /**
     * @return iterable<string, mixed[]>
     */
    public static function provideMatchTestCases(): iterable
    {
        // OPERATOR_EQ
        yield 'EQ: equal tax amount / match' => [Rule::OPERATOR_EQ, [3.5, 1.5], 5.0, true];
        yield 'EQ: unequal tax amount / no match' => [Rule::OPERATOR_EQ, [3.5, 1.5], 4.0, false];

        // OPERATOR_NEQ
        yield 'NEQ: unequal tax amount / match' => [Rule::OPERATOR_NEQ, [3.5, 1.5], 4.0, true];
        yield 'NEQ: equal tax amount / no match' => [Rule::OPERATOR_NEQ, [3.5, 1.5], 5.0, false];

        // OPERATOR_GT
        yield 'GT: cart tax higher than amount / match' => [Rule::OPERATOR_GT, [3.5, 1.5], 4.0, true];
        yield 'GT: cart tax equals amount / no match' => [Rule::OPERATOR_GT, [3.5, 1.5], 5.0, false];
        yield 'GT: cart tax lower than amount / no match' => [Rule::OPERATOR_GT, [3.5, 1.5], 6.0, false];

        // OPERATOR_GTE
        yield 'GTE: cart tax higher than amount / match' => [Rule::OPERATOR_GTE, [3.5, 1.5], 4.0, true];
        yield 'GTE: cart tax equals amount / match' => [Rule::OPERATOR_GTE, [3.5, 1.5], 5.0, true];
        yield 'GTE: cart tax lower than amount / no match' => [Rule::OPERATOR_GTE, [3.5, 1.5], 6.0, false];

        // OPERATOR_LT
        yield 'LT: cart tax lower than amount / match' => [Rule::OPERATOR_LT, [3.5, 1.5], 6.0, true];
        yield 'LT: cart tax equals amount / no match' => [Rule::OPERATOR_LT, [3.5, 1.5], 5.0, false];
        yield 'LT: cart tax higher than amount / no match' => [Rule::OPERATOR_LT, [3.5, 1.5], 4.0, false];

        // OPERATOR_LTE
        yield 'LTE: cart tax lower than amount / match' => [Rule::OPERATOR_LTE, [3.5, 1.5], 6.0, true];
        yield 'LTE: cart tax equals amount / match' => [Rule::OPERATOR_LTE, [3.5, 1.5], 5.0, true];
        yield 'LTE: cart tax higher than amount / no match' => [Rule::OPERATOR_LTE, [3.5, 1.5], 4.0, false];
    }

    public function testMatchWithZeroTax(): void
    {
        $rule = new CartTotalTaxRule();
        $rule->assign(['operator' => Rule::OPERATOR_EQ, 'amount' => 0.0]);

        $scope = new CartRuleScope(
            $this->createCartWithTaxes([]),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertTrue($rule->match($scope));
    }

    public function testConstraints(): void
    {
        $constraints = (new CartTotalTaxRule())->getConstraints();

        static::assertCount(2, $constraints);
        static::assertArrayHasKey('operator', $constraints);
        static::assertArrayHasKey('amount', $constraints);
    }

    public function testGetConfig(): void
    {
        $config = (new CartTotalTaxRule())->getConfig();
        $configData = $config->getData();

        static::assertArrayHasKey('operatorSet', $configData);
        static::assertSame([
            'operators' => RuleConfig::OPERATOR_SET_NUMBER,
            'isMatchAny' => false,
        ], $configData['operatorSet']);

        static::assertArrayHasKey('fields', $configData);
        static::assertArrayHasKey('amount', $configData['fields']);
        static::assertSame([
            'name' => 'amount',
            'type' => 'float',
            'config' => [
                'digits' => RuleConfig::DEFAULT_DIGITS,
            ],
        ], $configData['fields']['amount']);
    }

    /**
     * @param float[] $taxAmounts
     */
    private function createCartWithTaxes(array $taxAmounts): Cart
    {
        $calculatedTaxes = new CalculatedTaxCollection();
        foreach ($taxAmounts as $index => $taxAmount) {
            $calculatedTaxes->add(new CalculatedTax($taxAmount, (float) ($index + 1) * 7, 100.0));
        }

        $cartPrice = new CartPrice(
            100.0,
            119.0,
            100.0,
            $calculatedTaxes,
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS
        );

        $cart = new Cart('test-token');
        $cart->setPrice($cartPrice);

        return $cart;
    }
}

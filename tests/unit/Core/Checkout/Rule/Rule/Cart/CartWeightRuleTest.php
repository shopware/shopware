<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartWeightRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CartWeightRule::class)]
class CartWeightRuleTest extends TestCase
{
    public function testMatchWithWrongScopeShouldReturnFalse(): void
    {
        $cartVolumeRule = new CartWeightRule();

        $wrongScope = $this->createMock(RuleScope::class);

        static::assertFalse($cartVolumeRule->match($wrongScope));
    }

    #[DataProvider('matchTestDataProvider')]
    public function testMatch(string $operator, float $weight, bool $expectedResult): void
    {
        $cartVolumeRule = new CartWeightRule($operator, $weight);

        $cart = Generator::createCartWithDelivery();
        $context = $this->createMock(SalesChannelContext::class);

        $cartRuleScope = new CartRuleScope($cart, $context);

        static::assertSame($expectedResult, $cartVolumeRule->match($cartRuleScope));
    }

    /**
     * @return \Generator<array{0:string, 1:float, 2:bool}>
     */
    public static function matchTestDataProvider(): \Generator
    {
        yield '>= 200.0 true' => [
            Rule::OPERATOR_GTE,
            200.0,
            true,
        ];

        yield '>= 270.0 true' => [
            Rule::OPERATOR_GTE,
            270.0,
            true,
        ];

        yield '>= 300.0 false' => [
            Rule::OPERATOR_GTE,
            300.0,
            false,
        ];

        yield '<= 200.0 false' => [
            Rule::OPERATOR_LTE,
            200.0,
            false,
        ];

        yield '<= 270.0 true' => [
            Rule::OPERATOR_LTE,
            270.0,
            true,
        ];

        yield '<= 300.0 true' => [
            Rule::OPERATOR_LTE,
            300.0,
            true,
        ];

        yield '> 200.0 true' => [
            Rule::OPERATOR_GT,
            200.0,
            true,
        ];

        yield '> 270.0 false' => [
            Rule::OPERATOR_GT,
            270.0,
            false,
        ];

        yield '> 300.0 false' => [
            Rule::OPERATOR_GT,
            300.0,
            false,
        ];

        yield '< 200.0 false' => [
            Rule::OPERATOR_LT,
            200.0,
            false,
        ];

        yield '< 270.0 false' => [
            Rule::OPERATOR_LT,
            270.0,
            false,
        ];

        yield '< 300.0 true' => [
            Rule::OPERATOR_LT,
            300.0,
            true,
        ];

        yield '= 200.0 false' => [
            Rule::OPERATOR_EQ,
            200.0,
            false,
        ];

        yield '= 270.0 true' => [
            Rule::OPERATOR_EQ,
            270.0,
            true,
        ];

        yield '= 300.0 false' => [
            Rule::OPERATOR_EQ,
            300.0,
            false,
        ];

        yield '!= 200.0 true' => [
            Rule::OPERATOR_NEQ,
            200.0,
            true,
        ];

        yield '!= 270.0 false' => [
            Rule::OPERATOR_NEQ,
            270.0,
            false,
        ];

        yield '!= 300.0 true' => [
            Rule::OPERATOR_NEQ,
            300.0,
            true,
        ];

        yield 'empty 270.0 false' => [
            Rule::OPERATOR_EMPTY,
            270.0,
            false,
        ];
    }

    public function testGetConstraints(): void
    {
        $cartVolumeRule = new CartWeightRule();

        $result = $cartVolumeRule->getConstraints();

        static::assertArrayHasKey('weight', $result);
        static::assertArrayHasKey('operator', $result);

        static::assertIsArray($result['weight']);
        static::assertIsArray($result['operator']);
    }

    public function testGetConfig(): void
    {
        $cartVolumeRule = new CartWeightRule();

        $result = $cartVolumeRule->getConfig()->getData();

        static::assertIsArray($result['operatorSet']['operators']);
        static::assertSame('weight', $result['fields']['weight']['config']['unit']);
    }
}

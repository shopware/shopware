<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Rule\Rule\Cart;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\CartVolumeRule;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Exception\UnsupportedValueException;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(CartVolumeRule::class)]
class CartVolumeRuleTest extends TestCase
{
    public function testMatchWithWrongScopeShouldReturnFalse(): void
    {
        $cartVolumeRule = new CartVolumeRule();

        $wrongScope = $this->createMock(RuleScope::class);

        static::assertFalse($cartVolumeRule->match($wrongScope));
    }

    public function testMatchWithNullVolumeShouldThrowException(): void
    {
        $cartVolumeRule = new CartVolumeRule();

        $cartRuleScope = $this->createMock(CartRuleScope::class);

        $this->expectException(UnsupportedValueException::class);
        $this->expectExceptionMessage('Unsupported value of type NULL in Shopware\Core\Checkout\Cart\Rule\CartVolumeRule');

        $cartVolumeRule->match($cartRuleScope);
    }

    #[DataProvider('matchTestDataProvider')]
    public function testMatch(string $operator, float $volume, bool $expectedResult): void
    {
        $cartVolumeRule = new CartVolumeRule($operator, $volume);

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
        yield '>= 4000.0 true' => [
            Rule::OPERATOR_GTE,
            4000.0,
            true,
        ];

        yield '>= 5000.0 true' => [
            Rule::OPERATOR_GTE,
            5000.0,
            true,
        ];

        yield '>= 6000.0 false' => [
            Rule::OPERATOR_GTE,
            6000.0,
            false,
        ];

        yield '<= 4000.0 false' => [
            Rule::OPERATOR_LTE,
            4000.0,
            false,
        ];

        yield '<= 5000.0 true' => [
            Rule::OPERATOR_LTE,
            5000.0,
            true,
        ];

        yield '<= 6000.0 true' => [
            Rule::OPERATOR_LTE,
            6000.0,
            true,
        ];

        yield '> 4000.0 true' => [
            Rule::OPERATOR_GT,
            4000.0,
            true,
        ];

        yield '> 5000.0 false' => [
            Rule::OPERATOR_GT,
            5000.0,
            false,
        ];

        yield '> 6000.0 false' => [
            Rule::OPERATOR_GT,
            6000.0,
            false,
        ];

        yield '< 4000.0 false' => [
            Rule::OPERATOR_LT,
            4000.0,
            false,
        ];

        yield '< 5000.0 false' => [
            Rule::OPERATOR_LT,
            5000.0,
            false,
        ];

        yield '< 6000.0 true' => [
            Rule::OPERATOR_LT,
            6000.0,
            true,
        ];

        yield '= 4000.0 false' => [
            Rule::OPERATOR_EQ,
            4000.0,
            false,
        ];

        yield '= 5000.0 true' => [
            Rule::OPERATOR_EQ,
            5000.0,
            true,
        ];

        yield '= 6000.0 false' => [
            Rule::OPERATOR_EQ,
            6000.0,
            false,
        ];

        yield '!= 4000.0 true' => [
            Rule::OPERATOR_NEQ,
            4000.0,
            true,
        ];

        yield '!= 5000.0 false' => [
            Rule::OPERATOR_NEQ,
            5000.0,
            false,
        ];

        yield '!= 6000.0 true' => [
            Rule::OPERATOR_NEQ,
            6000.0,
            true,
        ];

        yield 'empty 5000.0 false' => [
            Rule::OPERATOR_EMPTY,
            5000.0,
            false,
        ];
    }

    public function testGetConstraints(): void
    {
        $cartVolumeRule = new CartVolumeRule();

        $result = $cartVolumeRule->getConstraints();

        static::assertArrayHasKey('volume', $result);
        static::assertArrayHasKey('operator', $result);

        static::assertIsArray($result['volume']);
        static::assertIsArray($result['operator']);
    }

    public function testGetConfig(): void
    {
        $cartVolumeRule = new CartVolumeRule();

        $result = $cartVolumeRule->getConfig()->getData();

        static::assertIsArray($result['operatorSet']['operators']);
        static::assertSame('volume', $result['fields']['volume']['config']['unit']);
    }
}

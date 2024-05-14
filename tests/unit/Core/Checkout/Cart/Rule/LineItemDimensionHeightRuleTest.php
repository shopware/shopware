<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionHeightRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(LineItemDimensionHeightRule::class)]
class LineItemDimensionHeightRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    #[DataProvider('matchTestDataProvider')]
    public function testMatch(string $operator, float $amount, bool $expectedResult): void
    {
        $lineItemDimensionHeightRule = new LineItemDimensionHeightRule($operator, $amount);

        $lineItemScope = new LineItemScope(
            static::createLineItemWithDeliveryInfo(true, 1, 10.0, 10.0, 10.0, 10.0),
            $this->createMock(SalesChannelContext::class)
        );

        static::assertSame($expectedResult, $lineItemDimensionHeightRule->match($lineItemScope));
    }

    public static function matchTestDataProvider(): \Generator
    {
        yield '>= 10.0 true' => [
            Rule::OPERATOR_GTE,
            10.0,
            true,
        ];

        yield '>= 12.0 false' => [
            Rule::OPERATOR_GTE,
            12.0,
            false,
        ];

        yield '<= 8.0 false' => [
            Rule::OPERATOR_LTE,
            8.0,
            false,
        ];

        yield '<= 10.0 true' => [
            Rule::OPERATOR_LTE,
            10.0,
            true,
        ];

        yield '<= 12.0 true' => [
            Rule::OPERATOR_LTE,
            12.0,
            true,
        ];

        yield '> 8.0 true' => [
            Rule::OPERATOR_GT,
            8.0,
            true,
        ];

        yield '> 10.0 false' => [
            Rule::OPERATOR_GT,
            10.0,
            false,
        ];

        yield '> 12.0 false' => [
            Rule::OPERATOR_GT,
            12.0,
            false,
        ];

        yield '< 8.0 false' => [
            Rule::OPERATOR_LT,
            8.0,
            false,
        ];

        yield '< 10.0 false' => [
            Rule::OPERATOR_LT,
            10.0,
            false,
        ];

        yield '< 12.0 true' => [
            Rule::OPERATOR_LT,
            12.0,
            true,
        ];

        yield '= 8.0 false' => [
            Rule::OPERATOR_EQ,
            8.0,
            false,
        ];

        yield '= 10.0 true' => [
            Rule::OPERATOR_EQ,
            10.0,
            true,
        ];

        yield '= 12.0 false' => [
            Rule::OPERATOR_EQ,
            12.0,
            false,
        ];

        yield '!= 8.0 true' => [
            Rule::OPERATOR_NEQ,
            8.0,
            true,
        ];

        yield '!= 10.0 false' => [
            Rule::OPERATOR_NEQ,
            10.0,
            false,
        ];

        yield '!= 12.0 true' => [
            Rule::OPERATOR_NEQ,
            12.0,
            true,
        ];

        yield 'empty 10.0 false' => [
            Rule::OPERATOR_EMPTY,
            10.0,
            false,
        ];
    }

    public function testMatchWithWrongScopeShouldReturnFalse(): void
    {
        $lineItemDimensionHeightRule = new LineItemDimensionHeightRule();
        $wrongScope = $this->createMock(RuleScope::class);

        static::assertFalse($lineItemDimensionHeightRule->match($wrongScope));
    }

    public function testMatchWithCartRuleScope(): void
    {
        $lineItemDimensionHeightRule = new LineItemDimensionHeightRule(Rule::OPERATOR_EQ, 10.0);

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add(static::createLineItemWithDeliveryInfo(true, 1, 10.0, 10.0, 10.0, 10.0));
        $lineItemCollection->add(static::createLineItemWithDeliveryInfo(true, 1, 10.0, 10.0, 10.0, 10.0));

        $cartRuleScope = new CartRuleScope(static::createCart($lineItemCollection), $this->createMock(SalesChannelContext::class));

        static::assertTrue($lineItemDimensionHeightRule->match($cartRuleScope));
    }

    public function testMatchWithCartRuleScopeExpectFalseBecauseLineItemIsHigher(): void
    {
        $lineItemDimensionHeightRule = new LineItemDimensionHeightRule(Rule::OPERATOR_EQ, 10.0);

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add(static::createLineItemWithDeliveryInfo(true, 1, 10.0, 12.0, 10.0, 10.0));
        $lineItemCollection->add(static::createLineItemWithDeliveryInfo(true, 1, 10.0, 12.0, 10.0, 10.0));

        $cartRuleScope = new CartRuleScope(static::createCart($lineItemCollection), $this->createMock(SalesChannelContext::class));

        static::assertFalse($lineItemDimensionHeightRule->match($cartRuleScope));
    }

    #[DataProvider('matchWithoutDeliveryInformationTestDataProvider')]
    public function testMatchWithoutDeliveryInformation(string $operator, bool $expectedResult): void
    {
        $lineItemDimensionHeightRule = new LineItemDimensionHeightRule($operator, 10.0);

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add(static::createLineItem('a'));
        $lineItemCollection->add(static::createLineItem('b'));

        $cartRuleScope = new CartRuleScope(static::createCart($lineItemCollection), $this->createMock(SalesChannelContext::class));

        static::assertSame($expectedResult, $lineItemDimensionHeightRule->match($cartRuleScope));
    }

    public static function matchWithoutDeliveryInformationTestDataProvider(): \Generator
    {
        yield 'empty expect true' => [
            Rule::OPERATOR_EMPTY,
            true,
        ];

        yield '!= expect true' => [
            Rule::OPERATOR_NEQ,
            true,
        ];

        yield '>= expect false' => [
            Rule::OPERATOR_GTE,
            false,
        ];

        yield '<= expect false' => [
            Rule::OPERATOR_LTE,
            false,
        ];

        yield '> expect false' => [
            Rule::OPERATOR_GT,
            false,
        ];

        yield '< expect false' => [
            Rule::OPERATOR_LT,
            false,
        ];

        yield '= expect false' => [
            Rule::OPERATOR_EQ,
            false,
        ];
    }

    public function testGetConstraintsWithOperatorEmpty(): void
    {
        $lineItemDimensionHeightRule = new LineItemDimensionHeightRule(Rule::OPERATOR_EMPTY);

        $result = $lineItemDimensionHeightRule->getConstraints();

        static::assertArrayHasKey('operator', $result);
        static::assertArrayNotHasKey('amount', $result);
    }

    public function testGetConstraintsWithOtherOperator(): void
    {
        $lineItemDimensionHeightRule = new LineItemDimensionHeightRule(Rule::OPERATOR_EQ);

        $result = $lineItemDimensionHeightRule->getConstraints();

        static::assertArrayHasKey('operator', $result);
        static::assertArrayHasKey('amount', $result);
    }

    public function testGetConfig(): void
    {
        $lineItemDimensionHeightRule = new LineItemDimensionHeightRule();

        $result = $lineItemDimensionHeightRule->getConfig()->getData();

        static::assertIsArray($result['operatorSet']['operators']);
        static::assertSame('dimension', $result['fields']['amount']['config']['unit']);
    }
}

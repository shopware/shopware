<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionWeightRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;
use Shopware\Tests\Unit\Core\Checkout\Customer\Rule\TestRuleScope;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(LineItemDimensionWeightRule::class)]
#[Group('rules')]
class LineItemDimensionWeightRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemDimensionWeightRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemDimensionWeightRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemDimensionWeight', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('amount', $ruleConstraints, 'Rule Constraint amount is not defined');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
    }

    #[DataProvider('getMatchingRuleTestData')]
    public function testIfMatchesCorrectWithLineItem(
        string $operator,
        float $weight,
        float $lineItemWeight,
        bool $expected,
        bool $lineItemWithoutDeliveryInfo = false
    ): void {
        $this->rule->assign([
            'amount' => $weight,
            'operator' => $operator,
        ]);

        $lineItem = $this->createLineItemWithWeight($lineItemWeight);
        if ($lineItemWithoutDeliveryInfo) {
            $lineItem = $this->createLineItem();
        }

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|int|bool|null>>
     */
    public static function getMatchingRuleTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same weight' => [Rule::OPERATOR_EQ, 100, 100, true];
        yield 'no match / operator equals / different weight' => [Rule::OPERATOR_EQ, 200, 100, false];
        yield 'no match / operator equals / without delivery info' => [Rule::OPERATOR_EQ, 200, 100, false, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same weight' => [Rule::OPERATOR_NEQ, 100, 100, false];
        yield 'match / operator not equals / different weight' => [Rule::OPERATOR_NEQ, 200, 100, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower weight' => [Rule::OPERATOR_GT, 100, 50, false];
        yield 'no match / operator greater than / same weight' => [Rule::OPERATOR_GT, 100, 100, false];
        yield 'match / operator greater than / higher weight' => [Rule::OPERATOR_GT, 100, 200, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower weight' => [Rule::OPERATOR_GTE, 100, 50, false];
        yield 'match / operator greater than equals / same weight' => [Rule::OPERATOR_GTE, 100, 100, true];
        yield 'match / operator greater than equals / higher weight' => [Rule::OPERATOR_GTE, 100, 200, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower weight' => [Rule::OPERATOR_LT, 100, 50, true];
        yield 'no match / operator lower  than / same weight' => [Rule::OPERATOR_LT, 100, 100, false];
        yield 'no match / operator lower than / higher weight' => [Rule::OPERATOR_LT, 100, 200, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower weight' => [Rule::OPERATOR_LTE, 100, 50, true];
        yield 'match / operator lower than equals / same weight' => [Rule::OPERATOR_LTE, 100, 100, true];
        yield 'no match / operator lower than equals / higher weight' => [Rule::OPERATOR_LTE, 100, 200, false];
        // OPERATOR_EMPTY
        yield 'no match / operator empty / weight' => [Rule::OPERATOR_EMPTY, 100, 200, false];

        yield 'match / operator not equals / without delivery info' => [Rule::OPERATOR_NEQ, 200, 100, true, true];
        yield 'match / operator empty / without delivery info' => [Rule::OPERATOR_EMPTY, 100, 200, true, true];
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        ?float $weight,
        ?float $lineItemWeight1,
        ?float $lineItemWeight2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false
    ): void {
        $this->rule->assign([
            'amount' => $weight,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithWeight($lineItemWeight1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = $this->createLineItem();
        }

        $lineItem2 = $this->createLineItemWithWeight($lineItemWeight2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = $this->createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);
        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    #[DataProvider('getCartRuleScopeTestData')]
    public function testIfMatchesCorrectWithCartRuleScopeNested(
        string $operator,
        ?float $weight,
        ?float $lineItemWeight1,
        ?float $lineItemWeight2,
        bool $expected,
        bool $lineItem1WithoutDeliveryInfo = false,
        bool $lineItem2WithoutDeliveryInfo = false,
        ?float $containerLineItemWeight = null
    ): void {
        $this->rule->assign([
            'amount' => $weight,
            'operator' => $operator,
        ]);

        $lineItem1 = $this->createLineItemWithWeight($lineItemWeight1);
        if ($lineItem1WithoutDeliveryInfo) {
            $lineItem1 = $this->createLineItem();
        }

        $lineItem2 = $this->createLineItemWithWeight($lineItemWeight2);
        if ($lineItem2WithoutDeliveryInfo) {
            $lineItem2 = $this->createLineItem();
        }

        $lineItemCollection = new LineItemCollection([
            $lineItem1,
            $lineItem2,
        ]);
        $containerLineItem = $this->createLineItem();
        if ($containerLineItemWeight !== null) {
            $containerLineItem = $this->createLineItemWithWeight($containerLineItemWeight);
        }
        $containerLineItem->setChildren($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return \Traversable<string, array<string|int|bool|null>>
     */
    public static function getCartRuleScopeTestData(): \Traversable
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same weight' => [Rule::OPERATOR_EQ, 100, 100, 200, true];
        yield 'no match / operator equals / different weight' => [Rule::OPERATOR_EQ, 200, 100, 300, false];
        yield 'no match / operator equals / item 1 without delivery info' => [Rule::OPERATOR_EQ, 200, 100, 300, false, true];
        yield 'no match / operator equals / item 2 without delivery info' => [Rule::OPERATOR_EQ, 200, 100, 300, false, false, true];
        yield 'no match / operator equals / item 1 and 2 without delivery info' => [Rule::OPERATOR_EQ, 200, 100, 300, false, true, true];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same weight' => [Rule::OPERATOR_NEQ, 100, 100, 100, false, false, false, 100];
        yield 'match / operator not equals / different weight' => [Rule::OPERATOR_NEQ, 200, 100, 200, true];
        yield 'match / operator not equals / different weight 2' => [Rule::OPERATOR_NEQ, 200, 100, 300, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower weight' => [Rule::OPERATOR_GT, 100, 50, 70, false];
        yield 'no match / operator greater than / same weight' => [Rule::OPERATOR_GT, 100, 100, 70, false];
        yield 'match / operator greater than / higher weight' => [Rule::OPERATOR_GT, 100, 200, 70, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower weight' => [Rule::OPERATOR_GTE, 100, 50, 70, false];
        yield 'match / operator greater than equals / same weight' => [Rule::OPERATOR_GTE, 100, 100, 70, true];
        yield 'match / operator greater than equals / higher weight' => [Rule::OPERATOR_GTE, 100, 200, 70, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower weight' => [Rule::OPERATOR_LT, 100, 50, 120, true];
        yield 'no match / operator lower  than / same weight' => [Rule::OPERATOR_LT, 100, 100, 120, false];
        yield 'no match / operator lower than / higher weight' => [Rule::OPERATOR_LT, 100, 200, 120, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower weight' => [Rule::OPERATOR_LTE, 100, 50, 120, true];
        yield 'match / operator lower than equals / same weight' => [Rule::OPERATOR_LTE, 100, 100, 120, true];
        yield 'no match / operator lower than equals / higher weight' => [Rule::OPERATOR_LTE, 100, 200, 120, false];
        // OPERATOR_EMPTY
        yield 'no match / operator empty / with weight' => [Rule::OPERATOR_EMPTY, null, 200, 120, false, false, false, 100];

        yield 'match / operator not equals / item 1 and 2 without delivery info' => [Rule::OPERATOR_NEQ, 200, 100, 300, true, true, true];
        yield 'match / operator not equals / item 1 without delivery info' => [Rule::OPERATOR_NEQ, 100, 100, 100, true, true];
        yield 'match / operator not equals / item 2 without delivery info' => [Rule::OPERATOR_NEQ, 100, 100, 100, true, false, true];

        yield 'match / operator empty / item 1 and 2 without delivery info' => [Rule::OPERATOR_EMPTY, 200, 100, 300, true, true, true];
        yield 'match / operator empty / item 1 without delivery info' => [Rule::OPERATOR_EMPTY, 100, 100, 100, true, true];
        yield 'match / operator empty / item 2 without delivery info' => [Rule::OPERATOR_EMPTY, 100, 100, 100, true, false, true];
    }

    public function testMatchWithUnsupportedScopeShouldReturnFalse(): void
    {
        $scope = new TestRuleScope($this->createMock(SalesChannelContext::class));

        $lineItemDimensionWeightRule = new LineItemDimensionWeightRule();

        static::assertFalse($lineItemDimensionWeightRule->match($scope));
    }

    public function testGetConstraintsWithEmptyOperator(): void
    {
        $lineItemDimensionWeightRule = new LineItemDimensionWeightRule(Rule::OPERATOR_EMPTY);

        $result = $lineItemDimensionWeightRule->getConstraints();

        static::assertInstanceOf(NotBlank::class, $result['operator'][0]);
        static::assertInstanceOf(Choice::class, $result['operator'][1]);
        static::assertIsArray($result['operator'][1]->choices);
        static::assertContains('empty', $result['operator'][1]->choices);
    }

    public function testGetConfig(): void
    {
        $lineItemDimensionWeightRule = new LineItemDimensionWeightRule();
        $result = $lineItemDimensionWeightRule->getConfig();

        $expectedOperatorSet = array_merge(RuleConfig::OPERATOR_SET_NUMBER, [Rule::OPERATOR_EMPTY]);

        static::assertSame($expectedOperatorSet, $result->getData()['operatorSet']['operators']);
        static::assertSame(RuleConfig::UNIT_WEIGHT, $result->getData()['fields']['amount']['config']['unit']);
    }

    private function createLineItemWithWeight(?float $weight): LineItem
    {
        return $this->createLineItemWithDeliveryInfo(false, 1, $weight);
    }
}

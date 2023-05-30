<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemActualStockRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @group rules
 */
#[Package('business-ops')]
class LineItemActualStockRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemActualStockRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemActualStockRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemActualStock', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('stock', $ruleConstraints, 'Rule Constraint stock is not defined');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItem(
        string $operator,
        int $stock,
        int $lineItemStock,
        bool $expected
    ): void {
        $this->rule->assign([
            'stock' => $stock,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            ($this->createLineItemWithStock(999))->setPayloadValue('stock', $lineItemStock),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public static function getMatchingRuleTestData(): \Generator
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same stock' => [Rule::OPERATOR_EQ, 100, 100, true];
        yield 'no match / operator equals / different stock' => [Rule::OPERATOR_EQ, 200, 100, false];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same stock' => [Rule::OPERATOR_NEQ, 100, 100, false];
        yield 'match / operator not equals / different stock' => [Rule::OPERATOR_NEQ, 200, 100, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower stock' => [Rule::OPERATOR_GT, 100, 50, false];
        yield 'no match / operator greater than / same stock' => [Rule::OPERATOR_GT, 100, 100, false];
        yield 'match / operator greater than / higher stock' => [Rule::OPERATOR_GT, 100, 200, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower stock' => [Rule::OPERATOR_GTE, 100, 50, false];
        yield 'match / operator greater than equals / same stock' => [Rule::OPERATOR_GTE, 100, 100, true];
        yield 'match / operator greater than equals / higher stock' => [Rule::OPERATOR_GTE, 100, 200, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower stock' => [Rule::OPERATOR_LT, 100, 50, true];
        yield 'no match / operator lower  than / same stock' => [Rule::OPERATOR_LT, 100, 100, false];
        yield 'no match / operator lower than / higher stock' => [Rule::OPERATOR_LT, 100, 200, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower stock' => [Rule::OPERATOR_LTE, 100, 50, true];
        yield 'match / operator lower than equals / same stock' => [Rule::OPERATOR_LTE, 100, 100, true];
        yield 'no match / operator lower than equals / higher stock' => [Rule::OPERATOR_LTE, 100, 200, false];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        int $stock,
        int $lineItemStock1,
        int $lineItemStock2,
        bool $expected
    ): void {
        $this->rule->assign([
            'stock' => $stock,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            ($this->createLineItemWithStock(999))->setPayloadValue('stock', $lineItemStock1),
            ($this->createLineItemWithStock(999))->setPayloadValue('stock', $lineItemStock2),
        ]);
        $cart = $this->createCart($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScopeNested(
        string $operator,
        int $stock,
        int $lineItemStock1,
        int $lineItemStock2,
        bool $expected
    ): void {
        $this->rule->assign([
            'stock' => $stock,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            ($this->createLineItemWithStock(999))->setPayloadValue('stock', $lineItemStock1),
            ($this->createLineItemWithStock(999))->setPayloadValue('stock', $lineItemStock2),
        ]);
        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public static function getCartRuleScopeTestData(): \Generator
    {
        // OPERATOR_EQ
        yield 'match / operator equals / same stock' => [Rule::OPERATOR_EQ, 100, 100, 200, true];
        yield 'no match / operator equals / different stock' => [Rule::OPERATOR_EQ, 200, 100, 300, false];
        // OPERATOR_NEQ
        yield 'no match / operator not equals / same stock' => [Rule::OPERATOR_NEQ, 100, 100, 100, false];
        yield 'match / operator not equals / different stock' => [Rule::OPERATOR_NEQ, 200, 100, 200, true];
        yield 'match / operator not equals / different stock 2' => [Rule::OPERATOR_NEQ, 200, 100, 300, true];
        // OPERATOR_GT
        yield 'no match / operator greater than / lower stock' => [Rule::OPERATOR_GT, 100, 50, 70, false];
        yield 'no match / operator greater than / same stock' => [Rule::OPERATOR_GT, 100, 100, 70, false];
        yield 'match / operator greater than / higher stock' => [Rule::OPERATOR_GT, 100, 200, 70, true];
        // OPERATOR_GTE
        yield 'no match / operator greater than equals / lower stock' => [Rule::OPERATOR_GTE, 100, 50, 70, false];
        yield 'match / operator greater than equals / same stock' => [Rule::OPERATOR_GTE, 100, 100, 70, true];
        yield 'match / operator greater than equals / higher stock' => [Rule::OPERATOR_GTE, 100, 200, 70, true];
        // OPERATOR_LT
        yield 'match / operator lower than / lower stock' => [Rule::OPERATOR_LT, 100, 50, 120, true];
        yield 'no match / operator lower  than / same stock' => [Rule::OPERATOR_LT, 100, 100, 120, false];
        yield 'no match / operator lower than / higher stock' => [Rule::OPERATOR_LT, 100, 200, 120, false];
        // OPERATOR_LTE
        yield 'match / operator lower than equals / lower stock' => [Rule::OPERATOR_LTE, 100, 50, 120, true];
        yield 'match / operator lower than equals / same stock' => [Rule::OPERATOR_LTE, 100, 100, 120, true];
        yield 'no match / operator lower than equals / higher stock' => [Rule::OPERATOR_LTE, 100, 200, 120, false];
    }

    /**
     * @throws CartException
     */
    public function testMatchWithEmptyDeliveryInformation(): void
    {
        $this->rule->assign(['stock' => 100, 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItem(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    private function createLineItemWithStock(int $stock): LineItem
    {
        return $this->createLineItemWithDeliveryInfo(false, 1, 1, null, null, null, $stock);
    }
}

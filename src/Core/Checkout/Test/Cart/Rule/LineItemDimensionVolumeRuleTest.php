<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Exception\InvalidQuantityException;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemDimensionVolumeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemDimensionVolumeRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemDimensionVolumeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemDimensionVolumeRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemDimensionVolume', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('amount', $ruleConstraints, 'Rule Constraint amount is not defined');
        static::assertArrayHasKey('operator', $ruleConstraints, 'Rule Constraint operator is not defined');
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItem(
        string $operator,
        float $volume,
        float $lineItemVolume,
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $volume,
            'operator' => $operator,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithVolume($lineItemVolume),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function getMatchingRuleTestData(): array
    {
        return [
            // OPERATOR_EQ
            'match / operator equals / same volume' => [Rule::OPERATOR_EQ, 100, 100, true],
            'no match / operator equals / different volume' => [Rule::OPERATOR_EQ, 200, 100, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same volume' => [Rule::OPERATOR_NEQ, 100, 100, false],
            'match / operator not equals / different volume' => [Rule::OPERATOR_NEQ, 200, 100, true],
            // OPERATOR_GT
            'no match / operator greater than / lower volume' => [Rule::OPERATOR_GT, 100, 50, false],
            'no match / operator greater than / same volume' => [Rule::OPERATOR_GT, 100, 100, false],
            'match / operator greater than / higher volume' => [Rule::OPERATOR_GT, 100, 200, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower volume' => [Rule::OPERATOR_GTE, 100, 50, false],
            'match / operator greater than equals / same volume' => [Rule::OPERATOR_GTE, 100, 100, true],
            'match / operator greater than equals / higher volume' => [Rule::OPERATOR_GTE, 100, 200, true],
            // OPERATOR_LT
            'match / operator lower than / lower volume' => [Rule::OPERATOR_LT, 100, 50, true],
            'no match / operator lower  than / same volume' => [Rule::OPERATOR_LT, 100, 100, false],
            'no match / operator lower than / higher volume' => [Rule::OPERATOR_LT, 100, 200, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower volume' => [Rule::OPERATOR_LTE, 100, 50, true],
            'match / operator lower than equals / same volume' => [Rule::OPERATOR_LTE, 100, 100, true],
            'no match / operator lower than equals / higher volume' => [Rule::OPERATOR_LTE, 100, 200, false],
        ];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(
        string $operator,
        float $volume,
        float $lineItemVolume1,
        float $lineItemVolume2,
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $volume,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithVolume($lineItemVolume1),
            $this->createLineItemWithVolume($lineItemVolume2),
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
        float $volume,
        float $lineItemVolume1,
        float $lineItemVolume2,
        bool $expected
    ): void {
        $this->rule->assign([
            'amount' => $volume,
            'operator' => $operator,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithVolume($lineItemVolume1),
            $this->createLineItemWithVolume($lineItemVolume2),
        ]);
        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function getCartRuleScopeTestData(): array
    {
        return [
            // OPERATOR_EQ
            'match / operator equals / same volume' => [Rule::OPERATOR_EQ, 100, 100, 200, true],
            'no match / operator equals / different volume' => [Rule::OPERATOR_EQ, 200, 100, 300, false],
            // OPERATOR_NEQ
            'no match / operator not equals / same volume' => [Rule::OPERATOR_NEQ, 100, 100, 100, false],
            'match / operator not equals / different volume' => [Rule::OPERATOR_NEQ, 200, 100, 200, true],
            'match / operator not equals / different volume 2' => [Rule::OPERATOR_NEQ, 200, 100, 300, true],
            // OPERATOR_GT
            'no match / operator greater than / lower volume' => [Rule::OPERATOR_GT, 100, 50, 70, false],
            'no match / operator greater than / same volume' => [Rule::OPERATOR_GT, 100, 100, 70, false],
            'match / operator greater than / higher volume' => [Rule::OPERATOR_GT, 100, 200, 70, true],
            // OPERATOR_GTE
            'no match / operator greater than equals / lower volume' => [Rule::OPERATOR_GTE, 100, 50, 70, false],
            'match / operator greater than equals / same volume' => [Rule::OPERATOR_GTE, 100, 100, 70, true],
            'match / operator greater than equals / higher volume' => [Rule::OPERATOR_GTE, 100, 200, 70, true],
            // OPERATOR_LT
            'match / operator lower than / lower volume' => [Rule::OPERATOR_LT, 100, 50, 120, true],
            'no match / operator lower  than / same volume' => [Rule::OPERATOR_LT, 100, 100, 120, false],
            'no match / operator lower than / higher volume' => [Rule::OPERATOR_LT, 100, 200, 120, false],
            // OPERATOR_LTE
            'match / operator lower than equals / lower volume' => [Rule::OPERATOR_LTE, 100, 50, 120, true],
            'match / operator lower than equals / same volume' => [Rule::OPERATOR_LTE, 100, 100, 120, true],
            'no match / operator lower than equals / higher volume' => [Rule::OPERATOR_LTE, 100, 200, 120, false],
        ];
    }

    /**
     * @throws InvalidQuantityException
     */
    public function testMatchWithEmptyDeliveryInformation(): void
    {
        $this->rule->assign(['amount' => 100, 'operator' => Rule::OPERATOR_EQ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItem(),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    private function createLineItemWithVolume(float $volume): LineItem
    {
        return $this->createLineItemWithDeliveryInfo(false, 1, 50, $volume, 1, 1);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemClearanceSaleRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 *
 * @group rules
 */
#[Package('business-ops')]
class LineItemClearanceSaleRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    private LineItemClearanceSaleRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemClearanceSaleRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemClearanceSale', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('clearanceSale', $ruleConstraints, 'Rule Constraint clearanceSale is not defined');
    }

    /**
     * @dataProvider getLineItemScopeTestData
     */
    public function testIfMatchesCorrectWithLineItemScope(bool $ruleActive, bool $clearanceSale, bool $expected): void
    {
        $this->rule->assign(['clearanceSale' => $ruleActive]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithClearance($clearanceSale),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, array<bool>>
     */
    public static function getLineItemScopeTestData(): array
    {
        return [
            'rule yes / clearance sale yes' => [true, true, true],
            'rule yes / clearance sale no' => [true, false, false],
            'rule no / clearance sale yes' => [false, true, false],
            'rule no / clearance sale no' => [false, false, true],
        ];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(bool $ruleActive, bool $clearanceSale, bool $expected): void
    {
        $this->rule->assign(['clearanceSale' => $ruleActive]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithClearance($clearanceSale),
            $this->createLineItemWithClearance(false),
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
    public function testIfMatchesCorrectWithCartRuleScopeNested(bool $ruleActive, bool $clearanceSale, bool $expected): void
    {
        $this->rule->assign(['clearanceSale' => $ruleActive]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithClearance($clearanceSale),
            $this->createLineItemWithClearance(false),
        ]);

        $containerLineItem = $this->createContainerLineItem($lineItemCollection);
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @return array<string, array<bool>>
     */
    public static function getCartRuleScopeTestData(): array
    {
        return [
            'rule yes / clearance sale yes' => [true, true, true],
            'rule yes / clearance sale no' => [true, false, false],
            'rule no / clearance sale no' => [false, false, true],
            'rule no / clearance sale yes' => [false, true, true],
        ];
    }

    private function createLineItemWithClearance(bool $clearanceSaleEnabled): LineItem
    {
        return $this->createLineItem()->setPayloadValue('isCloseout', $clearanceSaleEnabled);
    }
}

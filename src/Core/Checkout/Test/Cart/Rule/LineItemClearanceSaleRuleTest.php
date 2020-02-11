<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemClearanceSaleRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemClearanceSaleRuleTest extends TestCase
{
    /**
     * @var LineItemClearanceSaleRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemClearanceSaleRule();
    }

    public function testGetName(): void
    {
        static::assertEquals('cartLineItemClearanceSale', $this->rule->getName());
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
            $this->createLineItem($clearanceSale),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    public function getLineItemScopeTestData(): array
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

        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add($this->createLineItem($clearanceSale));
        $lineItemCollection->add($this->createLineItem(false));

        $cart->setLineItems($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    public function getCartRuleScopeTestData(): array
    {
        return [
            'rule yes / clearance sale yes' => [true, true, true],
            'rule yes / clearance sale no' => [true, false, false],
            'rule no / clearance sale no' => [false, false, true],
            'rule no / clearance sale yes' => [false, true, true],
        ];
    }

    private function createLineItem(bool $clearanceSaleEnabled): LineItem
    {
        return (new LineItem(Uuid::randomHex(), 'product', null, 3))
            ->setPayloadValue('isCloseout', $clearanceSaleEnabled);
    }
}

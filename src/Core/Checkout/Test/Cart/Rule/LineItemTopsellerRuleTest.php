<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemTopsellerRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemTopsellerRuleTest extends TestCase
{
    /**
     * @var LineItemTopsellerRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemTopsellerRule();
    }

    public function testGetName(): void
    {
        static::assertEquals('cartLineItemTopseller', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('isTopseller', $ruleConstraints, 'Rule Constraint isTopseller is not defined');
    }

    /**
     * @dataProvider getMatchingRuleTestData
     */
    public function testIfMatchesCorrectWithLineItem(bool $ruleActive, bool $markAsTopseller, bool $expected): void
    {
        $this->rule->assign(['isTopseller' => $ruleActive]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItem($markAsTopseller),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    public function getMatchingRuleTestData(): array
    {
        return [
            'rule yes / topseller yes' => [true, true, true],
            'rule yes / topseller no' => [true, false, false],
            'rule no / topseller yes' => [false, true, false],
            'rule no / topseller no' => [false, false, true],
        ];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(bool $ruleActive, bool $markAsTopseller, bool $expected): void
    {
        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add($this->createLineItem($markAsTopseller));
        $lineItemCollection->add($this->createLineItem(false));

        $cart->setLineItems($lineItemCollection);

        $this->rule->assign(['isTopseller' => $ruleActive]);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    public function getCartRuleScopeTestData(): array
    {
        return [
            'rule yes / novelty yes' => [true, true, true],
            'rule yes / novelty no' => [true, false, false],
            'rule no / novelty yes' => [false, true, true],
            'rule no / novelty no' => [false, false, true],
        ];
    }

    public function testMatchWithEmptyTopsellerPayload(): void
    {
        $this->rule->assign(['isTopseller' => true]);

        $match = $this->rule->match(new LineItemScope(
            new LineItem('dummy-article', 'product', null, 3),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    private function createLineItem(bool $markAsTopseller): LineItem
    {
        return (new LineItem(Uuid::randomHex(), 'product', null, 3))
            ->setPayloadValue('markAsTopseller', $markAsTopseller);
    }
}

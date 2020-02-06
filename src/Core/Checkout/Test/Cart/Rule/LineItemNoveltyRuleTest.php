<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemNoveltyRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemNoveltyRuleTest extends TestCase
{
    /**
     * @var LineItemNoveltyRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemNoveltyRule();
    }

    public function testGetName(): void
    {
        static::assertEquals('cartLineItemNovelty', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('isNovelty', $ruleConstraints, 'Rule Constraint isNovelty is not defined');
    }

    /**
     * @dataProvider getLineItemScopeTestData
     */
    public function testIfMatchesCorrectWithLineItem(bool $ruleActive, bool $isNew, bool $expected): void
    {
        $this->rule->assign(['isNovelty' => $ruleActive]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItem($isNew),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertEquals($expected, $match);
    }

    public function getLineItemScopeTestData(): array
    {
        return [
            'rule yes / novelty yes' => [true, true, true],
            'rule yes / novelty no' => [true, false, false],
            'rule no / novelty yes' => [false, true, false],
            'rule no / novelty no' => [false, false, true],
        ];
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testIfMatchesCorrectWithCartRuleScope(bool $ruleActive, bool $isNew, bool $expected): void
    {
        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add($this->createLineItem($isNew));
        $lineItemCollection->add($this->createLineItem(false));

        $cart->setLineItems($lineItemCollection);

        $this->rule->assign(['isNovelty' => $ruleActive]);

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

    private function createLineItem(bool $isNew): LineItem
    {
        return (new LineItem(Uuid::randomHex(), 'product', null, 3))
            ->setPayloadValue('isNew', $isNew);
    }
}

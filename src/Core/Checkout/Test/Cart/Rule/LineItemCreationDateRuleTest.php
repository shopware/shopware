<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemCreationDateRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemCreationDateRuleTest extends TestCase
{
    /**
     * @var LineItemCreationDateRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemCreationDateRule();
    }

    public function testGetName(): void
    {
        static::assertEquals('cartLineItemCreationDate', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('lineItemCreationDate', $ruleConstraints, 'Rule Constraint lineItemCreationDate is not defined');
    }

    public function testEmptyLineItemCreationDate(): void
    {
        $match = $this->rule->match(new LineItemScope(
            (new LineItem(Uuid::randomHex(), 'product', null, 3)),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testInvalidProvidedDateTimeString(): void
    {
        $this->rule->assign(['lineItemCreationDate' => 'wrong']);

        $match = $this->rule->match(new LineItemScope(
            (new LineItem(Uuid::randomHex(), 'product', null, 3)),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testLineItemMatchesDefinedCreationDate(): void
    {
        $this->rule->assign(['lineItemCreationDate' => '2020-02-06 00:00:00']);

        $lineItem = (new LineItem(Uuid::randomHex(), 'product', null, 3))
            ->setPayloadValue('createdAt', '2020-02-06 00:00:00');

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    public function testLineItemDoesNotMatchDefinedCreationDate(): void
    {
        $this->rule->assign(['lineItemCreationDate' => '2020-02-06 00:00:00']);

        $lineItem = (new LineItem(Uuid::randomHex(), 'product', null, 3))
            ->setPayloadValue('createdAt', '2020-02-05 23:59:00');

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testInvalidScope(): void
    {
        $this->rule->assign(['lineItemCreationDate' => '2020-02-06 00:00:00']);

        $match = $this->rule->match((new CheckoutRuleScope(
            $this->createMock(SalesChannelContext::class)
        )));

        static::assertFalse($match);
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testMultipleLineItemsInCartRuleScope(string $ruleCreationDate, string $lineItemCreationDate1, string $lineItemCreationDate2, bool $expected): void
    {
        $this->rule->assign(['lineItemCreationDate' => $ruleCreationDate]);

        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add($this->createLineItem($lineItemCreationDate1));
        $lineItemCollection->add($this->createLineItem($lineItemCreationDate2));

        $cart->setLineItems($lineItemCollection);

        $match = $this->rule->match((new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        )));

        static::assertEquals($expected, $match);
    }

    public function getCartRuleScopeTestData(): array
    {
        return [
            'no match' => ['2020-02-06 00:00:00', '2020-01-01 12:30:00', '2020-01-01 18:00:00', false],
            'one matching' => ['2020-02-06 00:00:00', '2020-02-06 12:30:00', '2020-01-01 18:00:00', true],
            'all matching' => ['2020-02-06 00:00:00', '2020-02-06 12:30:00', '2020-02-06 18:00:00', true],
        ];
    }

    private function createLineItem(string $createdAt): LineItem
    {
        return (new LineItem(Uuid::randomHex(), 'product', null, 3))
            ->setPayloadValue('createdAt', $createdAt);
    }
}

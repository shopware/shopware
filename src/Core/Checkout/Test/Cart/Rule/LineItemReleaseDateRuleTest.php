<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemReleaseDateRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @group rules
 */
class LineItemReleaseDateRuleTest extends TestCase
{
    /**
     * @var LineItemReleaseDateRule
     */
    private $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemReleaseDateRule();
    }

    public function testGetName(): void
    {
        static::assertEquals('cartLineItemReleaseDate', $this->rule->getName());
    }

    public function testGetConstraints(): void
    {
        $ruleConstraints = $this->rule->getConstraints();

        static::assertArrayHasKey('lineItemReleaseDate', $ruleConstraints, 'Rule Constraint lineItemReleaseDate is not defined');
    }

    public function testEmptylineItemReleaseDate(): void
    {
        $match = $this->rule->match(new LineItemScope(
            (new LineItem(Uuid::randomHex(), 'product', null, 3)),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testInvalidProvidedDateTimeString(): void
    {
        $this->rule->assign(['lineItemReleaseDate' => 'wrong']);

        $match = $this->rule->match(new LineItemScope(
            (new LineItem(Uuid::randomHex(), 'product', null, 3)),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testLineItemMatchesDefinedReleaseDate(): void
    {
        $this->rule->assign(['lineItemReleaseDate' => '2020-02-06 00:00:00']);

        $lineItem = (new LineItem(Uuid::randomHex(), 'product', null, 3))
            ->setPayloadValue('releaseDate', '2020-02-06 00:00:00');

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertTrue($match);
    }

    public function testLineItemDoesNotMatchDefinedReleaseDate(): void
    {
        $this->rule->assign(['lineItemReleaseDate' => '2020-02-06 00:00:00']);

        $lineItem = (new LineItem(Uuid::randomHex(), 'product', null, 3))
            ->setPayloadValue('releaseDate', '2020-02-05 23:59:00');

        $match = $this->rule->match(new LineItemScope(
            $lineItem,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertFalse($match);
    }

    public function testInvalidScope(): void
    {
        $this->rule->assign(['lineItemReleaseDate' => '2020-02-06 00:00:00']);

        $match = $this->rule->match((new CheckoutRuleScope(
            $this->createMock(SalesChannelContext::class)
        )));

        static::assertFalse($match);
    }

    /**
     * @dataProvider getCartRuleScopeTestData
     */
    public function testMultipleLineItemsInCartRuleScope(string $ruleCreationDate, string $lineItemReleaseDate1, string $lineItemReleaseDate2, bool $expected): void
    {
        $this->rule->assign(['lineItemReleaseDate' => $ruleCreationDate]);

        $cart = new Cart('test', Uuid::randomHex());

        $lineItemCollection = new LineItemCollection();
        $lineItemCollection->add($this->createLineItem($lineItemReleaseDate1));
        $lineItemCollection->add($this->createLineItem($lineItemReleaseDate2));

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

    private function createLineItem(string $releaseDate): LineItem
    {
        return (new LineItem(Uuid::randomHex(), 'product', null, 3))
            ->setPayloadValue('releaseDate', $releaseDate);
    }
}

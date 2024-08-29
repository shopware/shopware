<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemProductStatesRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Content\Product\State;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(LineItemProductStatesRule::class)]
class LineItemProductStatesRuleTest extends TestCase
{
    private LineItemProductStatesRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemProductStatesRule();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemProductStates', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('productState', $constraints);
        static::assertArrayHasKey('operator', $constraints);
        static::assertEquals(RuleConstraints::choice([
            State::IS_PHYSICAL,
            State::IS_DOWNLOAD,
        ]), $constraints['productState']);
        static::assertEquals(RuleConstraints::stringOperators(false), $constraints['operator']);
    }

    public function testConfig(): void
    {
        $config = $this->rule->getConfig();
        $expected = (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->selectField('productState', [
                State::IS_PHYSICAL,
                State::IS_DOWNLOAD,
            ]);

        static::assertEquals($expected->getData(), $config->getData());
    }

    /**
     * @param array<int, string> $states
     */
    #[DataProvider('caseDataProvider')]
    public function testMatchesWithLineItemScope(
        array $states,
        string $operator,
        string $productState,
        bool $expected
    ): void {
        $this->rule->assign([
            'operator' => $operator,
            'productState' => $productState,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithStates($states),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @param array<int, string> $states
     */
    #[DataProvider('caseDataProvider')]
    public function testMatchesWithCartRuleScope(
        array $states,
        string $operator,
        string $productState,
        bool $expected
    ): void {
        $this->rule->assign([
            'operator' => $operator,
            'productState' => $productState,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithStates($states),
        ]);

        $cart = new Cart('test-token');
        $cart->setLineItems($lineItemCollection);

        $match = $this->rule->match(new CartRuleScope(
            $cart,
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    public function testNotMatchingWithIncorrectScope(): void
    {
        $this->rule->assign([
            'operator' => Rule::OPERATOR_EQ,
            'productState' => State::IS_DOWNLOAD,
        ]);

        $match = $this->rule->match(new CheckoutRuleScope($this->createMock(SalesChannelContext::class)));

        static::assertFalse($match);
    }

    /**
     * @return array<string, array<int, array<int, string>|bool|string>>
     */
    public static function caseDataProvider(): array
    {
        return [
            'equal / match' => [[State::IS_PHYSICAL, State::IS_DOWNLOAD], Rule::OPERATOR_EQ, State::IS_DOWNLOAD, true],
            'equal / no match' => [[State::IS_PHYSICAL], Rule::OPERATOR_EQ, State::IS_DOWNLOAD, false],
            'not equal / match' => [[State::IS_PHYSICAL], Rule::OPERATOR_NEQ, State::IS_DOWNLOAD, true],
            'not equal / no match' => [[State::IS_PHYSICAL, State::IS_DOWNLOAD], Rule::OPERATOR_NEQ, State::IS_DOWNLOAD, false],
        ];
    }

    /**
     * @param array<int, string> $states
     */
    private function createLineItemWithStates(array $states): LineItem
    {
        return (new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE))
            ->setGood(true)
            ->setStates($states);
    }
}

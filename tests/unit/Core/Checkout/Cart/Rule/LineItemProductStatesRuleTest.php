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
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemProductStatesRule::class)]
class LineItemProductStatesRuleTest extends TestCase
{
    private LineItemProductStatesRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemProductStatesRule();
    }

    public function testConfigIsAbsentInMajorMode(): void
    {
        static::assertNull($this->rule->getConfig());
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testMatchingThrowsInMajorMode(): void
    {
        $this->expectException(FeatureException::class);
        $this->rule->match(static::createStub(RuleScope::class));
    }

    /**
     * @deprecated tag:v6.8.0 - Remove with the major feature flag.
     */
    public function testConstraintsThrowInMajorMode(): void
    {
        $this->expectException(FeatureException::class);
        $this->rule->getConstraints();
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemProductStates', $this->rule->getName());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
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

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testConfig(): void
    {
        $config = $this->rule->getConfig();
        static::assertNotNull($config);
        $expected = (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING, false, true)
            ->selectField('productState', [
                State::IS_PHYSICAL,
                State::IS_DOWNLOAD,
            ]);

        static::assertSame($expected->getData(), $config->getData());
    }

    /**
     * @param array<int, string> $states
     */
    #[DataProvider('caseDataProvider')]
    #[DisabledFeatures(['v6.8.0.0'])]
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
            static::createStub(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    /**
     * @param array<int, string> $states
     */
    #[DataProvider('caseDataProvider')]
    #[DisabledFeatures(['v6.8.0.0'])]
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
            static::createStub(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testNotMatchingWithIncorrectScope(): void
    {
        $this->rule->assign([
            'operator' => Rule::OPERATOR_EQ,
            'productState' => State::IS_DOWNLOAD,
        ]);

        $match = $this->rule->match(new CheckoutRuleScope(static::createStub(SalesChannelContext::class)));

        static::assertFalse($match);
    }

    /**
     * @return iterable<string, array<int, array<int, string>|bool|string>>
     */
    public static function caseDataProvider(): iterable
    {
        yield 'equal / match' => [[State::IS_PHYSICAL, State::IS_DOWNLOAD], Rule::OPERATOR_EQ, State::IS_DOWNLOAD, true];
        yield 'equal / no match' => [[State::IS_PHYSICAL], Rule::OPERATOR_EQ, State::IS_DOWNLOAD, false];
        yield 'not equal / match' => [[State::IS_PHYSICAL], Rule::OPERATOR_NEQ, State::IS_DOWNLOAD, true];
        yield 'not equal / no match' => [[State::IS_PHYSICAL, State::IS_DOWNLOAD], Rule::OPERATOR_NEQ, State::IS_DOWNLOAD, false];
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

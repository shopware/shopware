<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemProductTypeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductTypeRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Rule\RuleConfig;
use Shopware\Core\Framework\Rule\RuleConstraints;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[CoversClass(LineItemProductTypeRule::class)]
#[Package('fundamentals@after-sales')]
class LineItemProductTypeRuleTest extends TestCase
{
    private LineItemProductTypeRule $rule;

    protected function setUp(): void
    {
        $this->rule = new LineItemProductTypeRule(new ProductTypeRegistry([
            ProductDefinition::TYPE_PHYSICAL,
            ProductDefinition::TYPE_DIGITAL,
            'bundle',
        ]));
    }

    public function testGetName(): void
    {
        static::assertSame('cartLineItemProductType', $this->rule->getName());
    }

    public function testConstraints(): void
    {
        $constraints = $this->rule->getConstraints();

        static::assertArrayHasKey('productType', $constraints);
        static::assertArrayHasKey('operator', $constraints);
        static::assertEquals(RuleConstraints::choice([
            ProductDefinition::TYPE_PHYSICAL,
            ProductDefinition::TYPE_DIGITAL,
            'bundle',
        ]), $constraints['productType']);
        static::assertEquals(RuleConstraints::stringOperators(false), $constraints['operator']);
    }

    public function testConfigEmpty(): void
    {
        $config = (new LineItemProductTypeRule())->getConfig();

        $expected = (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->selectField('productType', [
                ProductDefinition::TYPE_PHYSICAL,
                ProductDefinition::TYPE_DIGITAL,
            ]);

        static::assertSame($expected->getData(), $config->getData());
    }

    public function testConfig(): void
    {
        $config = $this->rule->getConfig();
        $expected = (new RuleConfig())
            ->operatorSet(RuleConfig::OPERATOR_SET_STRING)
            ->selectField('productType', [
                ProductDefinition::TYPE_PHYSICAL,
                ProductDefinition::TYPE_DIGITAL,
                'bundle',
            ]);

        static::assertSame($expected->getData(), $config->getData());
    }

    #[DataProvider('caseDataProvider')]
    public function testMatchesWithLineItemScope(
        string $type,
        string $operator,
        string $productState,
        bool $expected
    ): void {
        $this->rule->assign([
            'operator' => $operator,
            'productType' => $productState,
        ]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithProductType($type),
            $this->createMock(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    #[DataProvider('caseDataProvider')]
    public function testMatchesWithCartRuleScope(
        string $type,
        string $operator,
        string $productState,
        bool $expected
    ): void {
        $this->rule->assign([
            'operator' => $operator,
            'productType' => $productState,
        ]);

        $lineItemCollection = new LineItemCollection([
            $this->createLineItemWithProductType($type),
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
            'productType' => ProductDefinition::TYPE_DIGITAL,
        ]);

        $match = $this->rule->match(new CheckoutRuleScope($this->createMock(SalesChannelContext::class)));

        static::assertFalse($match);
    }

    /**
     * @return iterable<string, array<int, bool|string>>
     */
    public static function caseDataProvider(): iterable
    {
        yield 'equal / match' => [ProductDefinition::TYPE_PHYSICAL, Rule::OPERATOR_EQ, ProductDefinition::TYPE_PHYSICAL, true];
        yield 'equal / no match' => [ProductDefinition::TYPE_PHYSICAL, Rule::OPERATOR_EQ, ProductDefinition::TYPE_DIGITAL, false];
        yield 'not equal / match' => [ProductDefinition::TYPE_PHYSICAL, Rule::OPERATOR_NEQ, ProductDefinition::TYPE_DIGITAL, true];
        yield 'not equal / no match' => [ProductDefinition::TYPE_DIGITAL, Rule::OPERATOR_NEQ, ProductDefinition::TYPE_DIGITAL, false];
    }

    private function createLineItemWithProductType(string $type): LineItem
    {
        return (new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE))
            ->setGood(true)
            ->setPayloadValue(LineItem::PAYLOAD_PRODUCT_TYPE, $type);
    }
}

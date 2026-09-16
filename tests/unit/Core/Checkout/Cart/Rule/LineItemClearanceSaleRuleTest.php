<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemClearanceSaleRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\RuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel\Helper\CartRuleHelperTrait;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemClearanceSaleRule::class)]
#[Group('rules')]
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

    #[DataProvider('getLineItemScopeTestData')]
    public function testIfMatchesCorrectWithLineItemScope(bool $ruleActive, bool $clearanceSale, bool $expected): void
    {
        $this->rule->assign(['clearanceSale' => $ruleActive]);

        $match = $this->rule->match(new LineItemScope(
            $this->createLineItemWithClearance($clearanceSale),
            static::createStub(SalesChannelContext::class)
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

    #[DataProvider('getCartRuleScopeTestData')]
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
            static::createStub(SalesChannelContext::class)
        ));

        static::assertSame($expected, $match);
    }

    #[DataProvider('getCartRuleScopeTestData')]
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
            static::createStub(SalesChannelContext::class)
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

    public function testMatchWithWrongScopeShouldReturnFalse(): void
    {
        $goodsCountRule = new LineItemClearanceSaleRule();
        $wrongScope = static::createStub(RuleScope::class);

        static::assertFalse($goodsCountRule->match($wrongScope));
    }

    public function testGetConfig(): void
    {
        $cartVolumeRule = new LineItemClearanceSaleRule();

        $result = $cartVolumeRule->getConfig()->getData();

        static::assertSame('clearanceSale', $result['fields']['clearanceSale']['name']);
    }

    #[DataProvider('lineItemTypeProvider')]
    public function testMatchesByLineItemType(string $type, bool $lineItemScope, bool $expected): void
    {
        $rule = new LineItemClearanceSaleRule(false);

        $lineItem = self::createLineItem($type);
        $context = static::createStub(SalesChannelContext::class);

        $scope = $lineItemScope
            ? new LineItemScope($lineItem, $context)
            : new CartRuleScope(self::createCart(new LineItemCollection([$lineItem])), $context);

        static::assertSame($expected, $rule->match($scope));
    }

    /**
     * @return \Generator<string, array{non-empty-string, bool, bool}>
     */
    public static function lineItemTypeProvider(): \Generator
    {
        yield 'product via line item scope' => [LineItem::PRODUCT_LINE_ITEM_TYPE, true, true];
        yield 'product via cart scope' => [LineItem::PRODUCT_LINE_ITEM_TYPE, false, true];
        yield 'custom via line item scope' => [LineItem::CUSTOM_LINE_ITEM_TYPE, true, false];
        yield 'custom via cart scope' => [LineItem::CUSTOM_LINE_ITEM_TYPE, false, false];
    }

    private function createLineItemWithClearance(bool $clearanceSaleEnabled): LineItem
    {
        return $this->createLineItem()->setPayloadValue('isCloseout', $clearanceSaleEnabled);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemPropertyRule;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleHelperTrait;
use Shopware\Core\Checkout\Test\Cart\Rule\Helper\CartRuleScopeCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('business-ops')]
class LineItemPropertyRuleTest extends TestCase
{
    use CartRuleHelperTrait;

    /**
     * @dataProvider cartRuleScopeProvider
     */
    public function testCartRuleScopes(CartRuleScopeCase $case): void
    {
        $cart = $this->createCart(new LineItemCollection($case->lineItems));

        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        static::assertSame($case->match, $case->rule->match($scope), $case->description);
    }

    /**
     * @dataProvider cartRuleScopeProvider
     */
    public function testCartRuleScopesNested(CartRuleScopeCase $case): void
    {
        $containerLineItem = $this->createContainerLineItem(new LineItemCollection($case->lineItems));
        $cart = $this->createCart(new LineItemCollection([$containerLineItem]));

        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        static::assertSame($case->match, $case->rule->match($scope), $case->description);
    }

    /**
     * @return array<array<CartRuleScopeCase>>
     */
    public static function cartRuleScopeProvider(): array
    {
        $emptyItem = self::createLineItemWithVariantOptions();
        $redItem = self::createLineItemWithVariantOptions(['red']);
        $greenItem = self::createLineItemWithVariantOptions(['green']);
        $blueGreenItem = self::createLineItemWithVariantOptions(['green', 'blue']);

        $emptyOptionItem = self::createLineItemWithVariantOptions();
        $redOptionItem = self::createLineItemWithVariantOptions([], ['red']);
        $greenOptionItem = self::createLineItemWithVariantOptions([], ['green']);
        $blueGreenOptionItem = self::createLineItemWithVariantOptions([], ['green', 'blue']);

        $mergeCase = self::createLineItemWithVariantOptions(['red'], ['green']);

        $cases = [
            new CartRuleScopeCase('empty cart', false, new LineItemPropertyRule(['red']), []),
            new CartRuleScopeCase('single property', true, new LineItemPropertyRule(['red']), [$redItem]),
            new CartRuleScopeCase('not matching rule property', false, new LineItemPropertyRule(['red']), [$greenItem]),
            new CartRuleScopeCase('Multiple property ids', true, new LineItemPropertyRule(['red']), [$greenItem, $redItem]),
            new CartRuleScopeCase('Multiple configured options', true, new LineItemPropertyRule(['red', 'green']), [$blueGreenItem]),
            new CartRuleScopeCase('Multiple configured properties without matching', false, new LineItemPropertyRule(['red', 'green']), [$emptyItem]),

            new CartRuleScopeCase('single option', true, new LineItemPropertyRule(['red']), [$redOptionItem]),
            new CartRuleScopeCase('not matching rule option', false, new LineItemPropertyRule(['red']), [$greenOptionItem]),
            new CartRuleScopeCase('Multiple option ids', true, new LineItemPropertyRule(['red']), [$greenOptionItem, $redOptionItem]),
            new CartRuleScopeCase('multiple option', true, new LineItemPropertyRule(['red', 'green']), [$blueGreenOptionItem]),
            new CartRuleScopeCase('multiple option', false, new LineItemPropertyRule(['red', 'green']), [$emptyOptionItem]),

            new CartRuleScopeCase('Merge case', true, new LineItemPropertyRule(['green']), [$mergeCase]),
        ];

        return array_map(static fn ($case) => [$case], $cases);
    }

    /**
     * @param array<string> $properties
     * @param array<string> $options
     */
    private static function createLineItemWithVariantOptions(array $properties = [], array $options = []): LineItem
    {
        $lineItem = self::createLineItem();

        $lineItem->setPayloadValue('propertyIds', $properties);
        $lineItem->setPayloadValue('optionIds', $options);

        return $lineItem;
    }
}

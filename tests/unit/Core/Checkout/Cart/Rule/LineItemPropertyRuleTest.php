<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemPropertyRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Checkout\CartRuleFixture;
use Shopware\Tests\Unit\Core\Checkout\Cart\Rule\Helper\CartRuleScopeCase;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemPropertyRule::class)]
class LineItemPropertyRuleTest extends TestCase
{
    #[DataProvider('cartRuleScopeProvider')]
    public function testCartRuleScopes(CartRuleScopeCase $case): void
    {
        $cart = CartRuleFixture::createCart(new LineItemCollection($case->lineItems));

        $scope = new CartRuleScope($cart, static::createStub(SalesChannelContext::class));

        static::assertSame($case->match, $case->rule->match($scope), $case->description);
    }

    #[DataProvider('cartRuleScopeProvider')]
    public function testCartRuleScopesNested(CartRuleScopeCase $case): void
    {
        $containerLineItem = CartRuleFixture::createContainerLineItem(new LineItemCollection($case->lineItems));
        $cart = CartRuleFixture::createCart(new LineItemCollection([$containerLineItem]));

        $scope = new CartRuleScope($cart, static::createStub(SalesChannelContext::class));

        static::assertSame($case->match, $case->rule->match($scope), $case->description);
    }

    /**
     * @return iterable<string, array<CartRuleScopeCase>>
     */
    public static function cartRuleScopeProvider(): iterable
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

        foreach ($cases as $case) {
            yield \sprintf('%s %s', $case->description, $case->match ? 'matches' : 'does not match') => [$case];
        }
    }

    #[DataProviderExternal(CartRuleFixture::class, 'lineItemWithoutProductDataProvider')]
    public function testLineItemWithoutProductData(string $type, bool $lineItemScope, bool $expected): void
    {
        $rule = new LineItemPropertyRule([Uuid::randomHex()], Rule::OPERATOR_NEQ);

        $lineItem = CartRuleFixture::createLineItem($type);
        $context = static::createStub(SalesChannelContext::class);

        $scope = $lineItemScope
            ? new LineItemScope($lineItem, $context)
            : new CartRuleScope(CartRuleFixture::createCart(new LineItemCollection([$lineItem])), $context);

        static::assertSame($expected, $rule->match($scope));
    }

    #[DataProvider('singlePayloadKeyProvider')]
    public function testLineItemWithOnePayloadKeyIsEvaluatedInCart(string $key): void
    {
        $rule = new LineItemPropertyRule([Uuid::randomHex()], Rule::OPERATOR_NEQ);

        $lineItem = CartRuleFixture::createLineItem('my-plugin-item')->setPayloadValue($key, []);

        static::assertTrue($rule->match(new CartRuleScope(
            CartRuleFixture::createCart(new LineItemCollection([$lineItem])),
            static::createStub(SalesChannelContext::class),
        )));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function singlePayloadKeyProvider(): iterable
    {
        yield 'only property ids' => ['propertyIds'];
        yield 'only option ids' => ['optionIds'];
    }

    public function testProductWithNullValuesIsEvaluatedInCart(): void
    {
        $rule = new LineItemPropertyRule([Uuid::randomHex()], Rule::OPERATOR_NEQ);
        $lineItem = CartRuleFixture::createLineItem()->setPayloadValue('propertyIds', null)->setPayloadValue('optionIds', null);
        $scope = new CartRuleScope(
            CartRuleFixture::createCart(new LineItemCollection([$lineItem])),
            static::createStub(SalesChannelContext::class),
        );

        static::assertTrue($rule->match($scope));
    }

    /**
     * @param array<string> $properties
     * @param array<string> $options
     */
    private static function createLineItemWithVariantOptions(array $properties = [], array $options = []): LineItem
    {
        $lineItem = CartRuleFixture::createLineItem();

        $lineItem->setPayloadValue('propertyIds', $properties);
        $lineItem->setPayloadValue('optionIds', $options);

        return $lineItem;
    }
}

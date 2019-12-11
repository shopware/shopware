<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemPropertyRule;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class LineItemPropertyRuleTest extends TestCase
{
    /**
     * @dataProvider cartRuleScopeProvider
     */
    public function testCartRuleScopes(CartRuleScopeCase $case): void
    {
        $cart = new Cart('test', 'test');
        $cart->setLineItems(new LineItemCollection($case->lineItems));

        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        static::assertSame($case->match, $case->rule->match($scope), $case->description);
    }

    public function cartRuleScopeProvider()
    {
        $emptyItem = $this->createLineItem();
        $redItem = $this->createLineItem(['red']);
        $greenItem = $this->createLineItem(['green']);
        $blueGreenItem = $this->createLineItem(['green', 'blue']);

        $emptyOptionItem = $this->createLineItem();
        $redOptionItem = $this->createLineItem([], ['red']);
        $greenOptionItem = $this->createLineItem([], ['green']);
        $blueGreenOptionItem = $this->createLineItem([], ['green', 'blue']);

        $mergeCase = $this->createLineItem(['red'], ['green']);

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

        return array_map(function ($case) {
            return [$case];
        }, $cases);
    }

    private function createLineItem(array $properties = [], array $options = [])
    {
        $lineItem = new LineItem(Uuid::randomHex(), 'test', Uuid::randomHex(), 1);

        $lineItem->setPayloadValue('propertyIds', $properties);
        $lineItem->setPayloadValue('optionIds', $options);

        return $lineItem;
    }
}

class CartRuleScopeCase
{
    /**
     * @var string
     */
    public $description;

    /**
     * @var bool
     */
    public $match;

    /**
     * @var array
     */
    public $lineItems;

    /**
     * @var LineItemPropertyRule
     */
    public $rule;

    public function __construct(string $description, bool $match, LineItemPropertyRule $rule, array $lineItems)
    {
        $this->match = $match;
        $this->rule = $rule;
        $this->lineItems = $lineItems;
        $this->description = $description;
    }
}

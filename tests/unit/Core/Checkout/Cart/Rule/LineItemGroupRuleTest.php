<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilder;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemGroupBuilderResult;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemGroupRule;
use Shopware\Core\Checkout\CheckoutRuleScope;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Type;

/**
 * @internal
 */
#[CoversClass(LineItemGroupRule::class)]
class LineItemGroupRuleTest extends TestCase
{
    public function testMatchReturnsFalseBecauseOfWrongScope(): void
    {
        $rule = new LineItemGroupRule();

        static::assertFalse($rule->match(new CheckoutRuleScope($this->createMock(SalesChannelContext::class))));
    }

    public function testMatchReturnsFalseBecauseOfWrongBuilder(): void
    {
        $rule = new LineItemGroupRule();
        $rule->assign(['groupId' => 'test', 'packagerKey' => 'test', 'value' => 1, 'sorterKey' => 'test']);

        $cart = new Cart('test');
        $cart->getData()->set(LineItemGroupBuilder::class, 'not a builder');
        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        static::assertFalse($rule->match($scope));
    }

    public function testMatchReturnsFalseBecauseOfEmptyResult(): void
    {
        $rule = new LineItemGroupRule();
        $rule->assign(['groupId' => 'test', 'packagerKey' => 'test', 'value' => 1, 'sorterKey' => 'test']);

        $cart = new Cart('test');
        $lineItemGroupBuilder = $this->createMock(LineItemGroupBuilder::class);
        $result = new LineItemGroupBuilderResult();
        $lineItemGroupBuilder->expects(static::once())->method('findGroupPackages')->willReturn($result);
        $cart->getData()->set(LineItemGroupBuilder::class, $lineItemGroupBuilder);
        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        static::assertFalse($rule->match($scope));
    }

    public function testMatchReturnsTrue(): void
    {
        $rule = new LineItemGroupRule();
        $rule->assign(['groupId' => 'test', 'packagerKey' => 'test', 'value' => 1, 'sorterKey' => 'test']);

        $cart = new Cart('test');
        $lineItemGroupBuilder = $this->createMock(LineItemGroupBuilder::class);
        $result = $this->createMock(LineItemGroupBuilderResult::class);
        $result->expects(static::once())->method('hasFoundItems')->willReturn(true);
        $lineItemGroupBuilder->expects(static::once())->method('findGroupPackages')->willReturn($result);
        $cart->getData()->set(LineItemGroupBuilder::class, $lineItemGroupBuilder);
        $scope = new CartRuleScope($cart, $this->createMock(SalesChannelContext::class));

        static::assertTrue($rule->match($scope));
    }

    public function testGetConstraints(): void
    {
        $constraints = (new LineItemGroupRule())->getConstraints();

        static::assertCount(5, $constraints);
        static::assertEquals([new NotBlank(), new Type('string')], $constraints['groupId']);
        static::assertEquals([new NotBlank(), new Type('string')], $constraints['packagerKey']);
        static::assertEquals([new NotBlank(), new Type('numeric')], $constraints['value']);
        static::assertEquals([new NotBlank(), new Type('string')], $constraints['sorterKey']);
        static::assertEquals([new NotBlank(), new Type('container')], $constraints['rules']);
    }
}

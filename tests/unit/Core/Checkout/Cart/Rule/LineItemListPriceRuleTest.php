<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Rule;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProviderExternal;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Checkout\Cart\Rule\LineItemListPriceRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Rule;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Checkout\CartRuleFixture;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(LineItemListPriceRule::class)]
class LineItemListPriceRuleTest extends TestCase
{
    #[DataProviderExternal(CartRuleFixture::class, 'lineItemTypeProvider')]
    public function testLineItemWithListPriceIsEvaluated(string $type, bool $lineItemScope): void
    {
        $rule = new LineItemListPriceRule(Rule::OPERATOR_EQ, 150.0);

        $lineItem = CartRuleFixture::createLineItemWithPrice($type, 100.0, ListPrice::createFromUnitPrice(100.0, 150.0));
        $context = static::createStub(SalesChannelContext::class);

        $scope = $lineItemScope
            ? new LineItemScope($lineItem, $context)
            : new CartRuleScope(CartRuleFixture::createCart(new LineItemCollection([$lineItem])), $context);

        static::assertTrue($rule->match($scope));
    }
}

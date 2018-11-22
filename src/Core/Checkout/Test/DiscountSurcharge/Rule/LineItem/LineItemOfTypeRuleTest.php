<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\DiscountSurcharge\Rule\LineItem;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Rule\LineItemOfTypeRule;
use Shopware\Core\Checkout\Cart\Rule\LineItemScope;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Content\Product\Cart\ProductCollector;

class LineItemOfTypeRuleTest extends TestCase
{
    public function testRuleWithProductTypeMatch(): void
    {
        $rule = (new LineItemOfTypeRule())->assign(['lineItemType' => ProductCollector::LINE_ITEM_TYPE]);

        $context = $this->createMock(CheckoutContext::class);

        static::assertTrue(
            $rule->match(new LineItemScope(new LineItem('A', 'product'), $context))->matches()
        );
    }

    public function testRuleWithProductTypeNotMatch(): void
    {
        $rule = (new LineItemOfTypeRule())->assign(['lineItemType' => 'voucher']);

        $context = $this->createMock(CheckoutContext::class);

        static::assertFalse(
            $rule->match(new LineItemScope(new LineItem('A', 'product'), $context))->matches()
        );
    }
}

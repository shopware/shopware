<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\LineItemQuantity;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\QuantityPriceDefinition;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Checkout\Promotion\Cart\Discount\DiscountLineItem;
use Shopware\Core\Checkout\Promotion\Cart\Discount\ScopePackager\CartScopeDiscountPackager;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
class CartScopeDiscountPackagerTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @dataProvider buildPackagesProvider
     *
     * @param LineItem[] $items
     * @param array<string> $expected
     */
    public function testBuildPackages(array $items, array $expected): void
    {
        $cart = new Cart('test');
        $cart->setLineItems(new LineItemCollection($items));

        $packager = $this->getContainer()->get(CartScopeDiscountPackager::class);

        $context = $this->createMock(SalesChannelContext::class);

        $discount = new DiscountLineItem('test', new QuantityPriceDefinition(10, new TaxRuleCollection([]), 1), [
            'discountScope' => 'scope',
            'discountType' => 'type',
            'filter' => [],
        ], null);

        $packages = $packager->getMatchingItems($discount, $cart, $context);

        $package = $packages->first();

        $ids = $package->getMetaData()->map(fn (LineItemQuantity $item) => $item->getLineItemId());

        static::assertEquals($expected, $ids);
    }

    public static function buildPackagesProvider(): \Generator
    {
        $stackable = new LineItem('stackable', LineItem::PRODUCT_LINE_ITEM_TYPE, null, 1);
        $stackable->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $stackable->setStackable(true);

        $other = new LineItem('other', LineItem::PRODUCT_LINE_ITEM_TYPE, null, 2);
        $other->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $other->setStackable(true);

        $none = new LineItem('none', LineItem::PRODUCT_LINE_ITEM_TYPE, null, 1);
        $none->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $none->setStackable(false);

        $credit = new LineItem('credit', LineItem::CREDIT_LINE_ITEM_TYPE, null, 1);
        $credit->setPrice(new CalculatedPrice(100, 100, new CalculatedTaxCollection(), new TaxRuleCollection()));
        $credit->setStackable(true);

        yield 'Items will be splitted' => [
            [$stackable, $other],
            ['stackable', 'other', 'other'],
        ];

        yield 'None stackable will not be considered' => [
            [$stackable, $other, $none],
            ['stackable', 'other', 'other'],
        ];

        yield 'None stackable items will not be splitted' => [
            [$stackable, $other, $credit],
            ['stackable', 'other', 'other'],
        ];
    }
}

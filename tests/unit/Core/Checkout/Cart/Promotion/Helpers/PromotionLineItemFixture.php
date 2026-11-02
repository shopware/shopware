<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Promotion\Helpers;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
final class PromotionLineItemFixture
{
    /**
     * Create a simple product line item with the provided price.
     *
     * The price is used as the unit price as is; the tax is only reported in the calculated taxes, not added on top.
     * {@see \Shopware\Tests\Unit\Core\Checkout\Cart\LineItem\Group\Helpers\LineItemFixture::createProductItem()} takes a net price and adds the tax.
     */
    public static function createProductItem(float $price, float $taxRate): LineItem
    {
        $product = new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE);

        // allow quantity change
        $product->setStackable(true);

        $taxValue = $price * ($taxRate / 100.0);

        $calculatedTaxes = new CalculatedTaxCollection();
        $calculatedTaxes->add(new CalculatedTax($taxValue, $taxRate, $taxValue));

        $product->setPrice(new CalculatedPrice($price, $price, $calculatedTaxes, new TaxRuleCollection()));

        return $product;
    }
}

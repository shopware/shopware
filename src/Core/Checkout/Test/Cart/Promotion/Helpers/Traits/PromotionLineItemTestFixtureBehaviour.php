<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Promotion\Helpers\Traits;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTax;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

#[Package('checkout')]
trait PromotionLineItemTestFixtureBehaviour
{
    /**
     * Create a simple product line item with the provided price.
     */
    private function createProductItem(float $price, float $taxRate): LineItem
    {
        $product = new LineItem(Uuid::randomBytes(), LineItem::PRODUCT_LINE_ITEM_TYPE);

        // allow quantity change
        $product->setStackable(true);

        $taxValue = $price * ($taxRate / 100.0);

        $calculatedTaxes = new CalculatedTaxCollection();
        $calculatedTaxes->add(new CalculatedTax($taxValue, $taxRate, $taxValue));

        $product->setPrice(new CalculatedPrice($price, $price, $calculatedTaxes, new TaxRuleCollection()));

        return $product;
    }
}

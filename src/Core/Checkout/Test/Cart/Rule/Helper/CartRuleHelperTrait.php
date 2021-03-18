<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\Rule\Helper;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryInformation;
use Shopware\Core\Checkout\Cart\Delivery\Struct\DeliveryTime;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\ListPrice;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Uuid\Uuid;

trait CartRuleHelperTrait
{
    protected function createLineItem(
        string $type = LineItem::PRODUCT_LINE_ITEM_TYPE,
        int $quantity = 1,
        ?string $referenceId = null
    ): LineItem {
        return new LineItem(Uuid::randomHex(), $type, $referenceId, $quantity);
    }

    protected function createLineItemWithDeliveryInfo(
        bool $freeDelivery,
        int $quantity = 1,
        float $weight = 50.0,
        ?float $height = null,
        ?float $width = null,
        ?float $length = null
    ): LineItem {
        return ($this->createLineItem(LineItem::PRODUCT_LINE_ITEM_TYPE, $quantity))->setDeliveryInformation(
            new DeliveryInformation(
                9999,
                $weight,
                $freeDelivery,
                null,
                (new DeliveryTime())->assign([
                    'min' => 1,
                    'max' => 3,
                    'unit' => 'weeks',
                    'name' => '1-3 weeks',
                ]),
                $height,
                $width,
                $length
            )
        );
    }

    protected function createContainerLineItem(LineItemCollection $childLineItemCollection): LineItem
    {
        return ($this->createLineItem('container-type'))->setChildren($childLineItemCollection);
    }

    protected function createLineItemWithPrice(string $type, float $price, ?ListPrice $listPrice = null): LineItem
    {
        return ($this->createLineItem($type))->setPrice(
            new CalculatedPrice(
                $price,
                $price,
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                1,
                null,
                $listPrice
            )
        );
    }

    protected function createCart(LineItemCollection $lineItemCollection): Cart
    {
        $cart = new Cart('test', Uuid::randomHex());
        $cart->addLineItems($lineItemCollection);

        return $cart;
    }
}

<?php declare(strict_types=1);

namespace Shopware\CartBridge\Voucher;

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\LineItem\LineItemInterface;
use Shopware\Cart\Price\AbsolutePriceCalculator;
use Shopware\Cart\Price\PercentagePriceCalculator;
use Shopware\CartBridge\Voucher\Struct\CalculatedVoucher;
use Shopware\CartBridge\Voucher\Struct\VoucherData;
use Shopware\Context\Struct\ShopContext;

class VoucherCalculator
{
    /**
     * @var PercentagePriceCalculator
     */
    private $percentagePriceCalculator;

    /**
     * @var AbsolutePriceCalculator
     */
    private $absolutePriceCalculator;

    public function __construct(
        PercentagePriceCalculator $percentagePriceCalculator,
        AbsolutePriceCalculator $absolutePriceCalculator
    ) {
        $this->percentagePriceCalculator = $percentagePriceCalculator;
        $this->absolutePriceCalculator = $absolutePriceCalculator;
    }

    public function calculate(
        CalculatedCart $calculatedCart,
        ShopContext $context,
        VoucherData $voucher,
        LineItemInterface $lineItem
    ): CalculatedVoucher {
        $prices = $calculatedCart->getCalculatedLineItems()->filterGoods()->getPrices();

        if ($voucher->getPercentage() !== null) {
            /** @var VoucherData $voucher */
            $discount = $this->percentagePriceCalculator->calculate(
                abs($voucher->getPercentage()) * -1,
                $prices,
                $context
            );
        } else {
            $price = $voucher->getAbsolute();
            $discount = $this->absolutePriceCalculator->calculate($price->getPrice(), $prices, $context);
        }

        return new CalculatedVoucher($lineItem->getIdentifier(), $lineItem, $discount, $voucher->getRule());
    }
}

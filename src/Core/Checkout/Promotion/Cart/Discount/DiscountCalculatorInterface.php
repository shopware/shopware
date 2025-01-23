<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Promotion\Cart\Discount;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
interface DiscountCalculatorInterface
{
    /**
     * This function should calculate a price depending on the type of the calculator.
     * It should use any configurations from the provided discount line item.
     * The price should then be calculated based on the provided target line items up until
     * the provided maximum cart value.
     * The resulting object should then contain the calculated price along with meta data
     * with all items that are discounted.
     *
     * Please use the provided list of targeted prices for any calculations to ensure correct
     * tax distribution. The list of target prices is already prepared with the correct
     * quantities and amounts for the discount.
     */
    public function calculate(DiscountLineItem $discount, DiscountPackageCollection $packages, SalesChannelContext $context): DiscountCalculatorResult;
}

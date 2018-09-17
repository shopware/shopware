<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\CheckoutContext;

class TaxDetector
{
    public function useGross(CheckoutContext $context): bool
    {
        return $context->getCurrentCustomerGroup()->getDisplayGross();
    }

    public function isNetDelivery(CheckoutContext $context): bool
    {
        return $context->getShippingLocation()->getCountry()->getTaxFree();
    }

    public function getTaxState(CheckoutContext $context): string
    {
        if ($this->isNetDelivery($context)) {
            return CartPrice::TAX_STATE_FREE;
        }

        if ($this->useGross($context)) {
            return CartPrice::TAX_STATE_GROSS;
        }

        return CartPrice::TAX_STATE_NET;
    }
}

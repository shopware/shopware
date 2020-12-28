<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class TaxDetector
{
    public function useGross(SalesChannelContext $context): bool
    {
        return $context->getCurrentCustomerGroup()->getDisplayGross();
    }

    public function isNetDelivery(SalesChannelContext $context): bool
    {
        $shippingLocationCountry = $context->getShippingLocation()->getCountry();
        if ($shippingLocationCountry->getTaxFree()) {
            return true;
        }

        $customer = $context->getCustomer();

        if (!$shippingLocationCountry->getCompanyTaxFree() || !$customer || !$customer->getCompany()) {
            return false;
        }

        $vatPattern = $shippingLocationCountry->getVatIdPattern();
        $vatIds = $customer->getVatIds();

        if ($vatPattern === null || empty($vatIds)) {
            return false;
        }

        $regex = '/^' . $vatPattern . '$/i';

        foreach ($vatIds as $vatId) {
            if (!preg_match($regex, $vatId)) {
                return false;
            }
        }

        return true;
    }

    public function getTaxState(SalesChannelContext $context): string
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

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Tax;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

#[Package('checkout')]
abstract class AbstractTaxDetector
{
    abstract public function getDecorated(): AbstractTaxDetector;

    abstract public function useGross(SalesChannelContext $context): bool;

    abstract public function isNetDelivery(SalesChannelContext $context): bool;

    abstract public function getTaxState(SalesChannelContext $context): string;

    abstract public function isCompanyTaxFree(SalesChannelContext $context, CountryEntity $shippingLocationCountry): bool;
}

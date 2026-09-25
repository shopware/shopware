<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart\Price;

use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupEntity;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('checkout')]
final class TaxRuleFingerprint
{
    /**
     * @return string|null the fingerprint of the resolved tax rates, or null when the customer group does not derive prices from them
     */
    public static function build(SalesChannelContext $context): ?string
    {
        if (!self::derivesPricesFromTaxRates($context)) {
            return null;
        }

        $rates = [];
        foreach ($context->getTaxRules() as $tax) {
            $rates[$tax->getId()] = $tax->getRules()?->first()?->getTaxRate() ?? $tax->getTaxRate();
        }

        ksort($rates);

        return Hasher::hash($rates);
    }

    private static function derivesPricesFromTaxRates(SalesChannelContext $context): bool
    {
        $basis = $context->getCurrentCustomerGroup()->getPriceBasis();

        return match ($context->getTaxState()) {
            CartPrice::TAX_STATE_GROSS => $basis === CustomerGroupEntity::PRICE_BASIS_NET,
            CartPrice::TAX_STATE_NET => $basis === CustomerGroupEntity::PRICE_BASIS_GROSS,
            default => false,
        };
    }
}

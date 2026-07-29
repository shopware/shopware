<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\Enum;

use Shopware\Core\Framework\Log\Package;

/**
 * Allowance and charge reason codes.
 *
 * Codes are drawn from two UN/EDIFACT lists depending on whether the entry is an allowance or a charge:
 *  - Allowances: UNCL 5189
 *  - Charges: UNCL 7161
 *
 * @see https://docs.peppol.eu/poacc/billing/3.0/codelist/UNCL5189/
 * @see https://docs.peppol.eu/poacc/billing/3.0/codelist/UNCL7161/
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
enum AllowanceChargeReason: string
{
    case DISCOUNT = '95';
    case DELIVERY = 'DL';

    public function defaultLabel(): string
    {
        return match ($this) {
            self::DISCOUNT => 'Discount',
            self::DELIVERY => 'Delivery',
        };
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DocumentV2\Template\Enum;

use Shopware\Core\Framework\Log\Package;

/**
 * UN/CEFACT codelist 5305 — Duty/tax/fee category code.
 *
 * @see https://service.unece.org/trade/untdid/d16b/tred/tred5305.htm
 *
 * @internal
 *
 * @codeCoverageIgnore
 */
#[Package('after-sales')]
enum TaxCategory: string
{
    case STANDARD_RATE = 'S';
    case ZERO_RATED = 'Z';
    case INTRA_COMMUNITY_SUPPLY = 'K';

    public static function fromRate(float $taxRate): self
    {
        return $taxRate > 0.0 ? self::STANDARD_RATE : self::ZERO_RATED;
    }

    /**
     * BT-121, from the VATEX code list
     */
    public function exemptionReasonCode(): ?string
    {
        return match ($this) {
            self::INTRA_COMMUNITY_SUPPLY => 'VATEX-EU-IC',
            default => null,
        };
    }

    /**
     * BT-120, untranslated like the other texts of the e-invoice
     */
    public function exemptionReason(): ?string
    {
        return match ($this) {
            self::INTRA_COMMUNITY_SUPPLY => 'Intra-community supply',
            default => null,
        };
    }
}

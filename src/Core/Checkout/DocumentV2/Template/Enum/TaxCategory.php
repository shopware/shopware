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

    public static function fromRate(float $taxRate): self
    {
        return $taxRate > 0.0 ? self::STANDARD_RATE : self::ZERO_RATED;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Garan;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('inventory')]
class GaranLabelDurationFormatter
{
    public function formatMonths(?int $guaranteeMonths): ?string
    {
        if ($guaranteeMonths === null || $guaranteeMonths <= 24 || $guaranteeMonths % 6 !== 0) {
            return null;
        }

        $fullYears = intdiv($guaranteeMonths, 12);

        if ($guaranteeMonths % 12 === 0) {
            return (string) $fullYears;
        }

        return $fullYears . ',5';
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\SalesChannel;

use Shopware\Core\Framework\Log\Package;

#[Package('checkout')]
trait CustomerVatIdNormalizerTrait
{
    /**
     * Strips formatting whitespace and normalizes casing, so customers can enter a VAT ID
     * the way they are used to (e.g. lower case or grouped with spaces) while it is still
     * matched against the country's case-sensitive `vat_id_pattern`.
     *
     * @param array<int|string, mixed> $vatIds
     *
     * @return array<int|string, mixed>
     */
    private function normalizeVatIds(array $vatIds): array
    {
        foreach ($vatIds as $key => $vatId) {
            if (!\is_string($vatId)) {
                continue;
            }

            $vatIds[$key] = mb_strtoupper((string) preg_replace('/\s+/u', '', $vatId));
        }

        return $vatIds;
    }
}

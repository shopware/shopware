<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class FingerprintComparisonResult
{
    public readonly int $score;

    /**
     * @param array<string, FingerprintMatch> $matchingFingerprints
     * @param array<string, FingerprintMismatch> $mismatchingFingerprints
     */
    public function __construct(
        public readonly array $matchingFingerprints,
        public readonly array $mismatchingFingerprints,
        public readonly int $threshold,
    ) {
        $this->score = array_sum(array_map(fn (FingerprintMismatch $mismatch) => $mismatch->score, $mismatchingFingerprints));
    }

    public function getMismatchingFingerprint(string $identifier): ?FingerprintMismatch
    {
        return $this->mismatchingFingerprints[$identifier] ?? null;
    }
}

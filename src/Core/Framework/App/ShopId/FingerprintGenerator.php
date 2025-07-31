<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
class FingerprintGenerator
{
    private const STATE_CHANGE_THRESHOLD = 75;

    /**
     * @var array<string, Fingerprint>
     */
    private array $fingerprints;

    /**
     * @param iterable<Fingerprint> $fingerprints
     */
    public function __construct(
        iterable $fingerprints,
    ) {
        foreach ($fingerprints as $fingerprint) {
            $this->fingerprints[$fingerprint->getIdentifier()] = $fingerprint;
        }
    }

    /**
     * @param array<string, string> $fingerprints
     */
    public function compare(array $fingerprints): void
    {
        $score = 0;
        $mismatchingFingerprints = [];

        foreach ($this->fingerprints as $fingerprint) {
            $stamp = $fingerprints[$fingerprint->getIdentifier()] ?? null;
            if ($stamp === $fingerprint->getStamp()) {
                continue;
            }

            $mismatchingFingerprints[] = $fingerprint->getIdentifier();
            $score += $fingerprint->getScore();
        }

        if ($score >= self::STATE_CHANGE_THRESHOLD) {
            throw AppException::shopIdChangeSuggested($mismatchingFingerprints);
        }
    }

    /**
     * @return array<string, string>
     */
    public function takeFingerprints(): array
    {
        $fingerprints = [];

        foreach ($this->fingerprints as $fingerprint) {
            $fingerprints[$fingerprint->getIdentifier()] = $fingerprint->getStamp();
        }

        return $fingerprints;
    }
}

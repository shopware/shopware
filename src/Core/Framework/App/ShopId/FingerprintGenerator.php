<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

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
    public function matchFingerprints(array $fingerprints): FingerprintComparisonResult
    {
        $matchingFingerprints = [];
        $mismatchingFingerprints = [];

        foreach ($this->fingerprints as $fingerprint) {
            $storedStamp = $fingerprints[$fingerprint->getIdentifier()] ?? null;
            // calculating a stamp can be expensive (e.g. reading all sales channel domains), so it is done once
            $stamp = $fingerprint->getStamp();

            if ($fingerprint instanceof FingerprintCustomCompare) {
                $score = $fingerprint->compare($storedStamp);

                if ($score === 0) {
                    $matchingFingerprints[$fingerprint->getIdentifier()] = FingerprintMatch::fromFingerprint($fingerprint, $stamp);
                    continue;
                }

                $mismatchingFingerprints[$fingerprint->getIdentifier()] = new FingerprintMismatch(
                    $fingerprint->getIdentifier(),
                    $storedStamp,
                    $stamp,
                    $score,
                );

                continue;
            }

            if ($storedStamp === $stamp) {
                $matchingFingerprints[$fingerprint->getIdentifier()] = FingerprintMatch::fromFingerprint($fingerprint, $stamp);
                continue;
            }

            $mismatchingFingerprints[$fingerprint->getIdentifier()] = new FingerprintMismatch(
                $fingerprint->getIdentifier(),
                $storedStamp,
                $stamp,
                $fingerprint->getScore(),
            );
        }

        return new FingerprintComparisonResult(
            $matchingFingerprints,
            $mismatchingFingerprints,
            self::STATE_CHANGE_THRESHOLD,
        );
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

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\ShopId;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Optional interface for Fingerprint implementations that want to provide custom comparison logic.
 * If a Fingerprint implements this interface, the compare method will be used instead of
 * a simple equality check.
 */
#[Package('framework')]
interface FingerprintCustomCompare
{
    /**
     * Custom comparison method to calculate a match score based on the difference between stamps.
     *
     * The method should return a score between 0 and 100. 0 indicates match, no shop id change required. 100 indicates shop id change absolutely required.
     *
     * @param string|null $storedStamp The previously stored stamp, or null if none exists
     */
    public function compare(?string $storedStamp): int;
}

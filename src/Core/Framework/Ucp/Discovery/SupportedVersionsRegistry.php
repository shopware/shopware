<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Discovery;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpVersion;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Provides the `supported_versions` map for the well-known profile. Older
 * versions are served as version-pinned, leaf profiles at
 * `/.well-known/ucp/{version}` — see {@see VersionedProfileController}.
 *
 * @internal
 */
#[Package('framework')]
class SupportedVersionsRegistry
{
    /**
     * @return array<string, string>
     */
    public function buildForBaseUri(string $baseUri): array
    {
        $out = [];
        foreach (UcpVersion::HISTORICAL as $historical) {
            // Defensive guard for the moment we bump UcpVersion::CURRENT into
            // HISTORICAL during a version transition — PHPStan sees the
            // comparison as always false today because HISTORICAL has not yet
            // overlapped with CURRENT, but it MUST stay in place.
            // @phpstan-ignore-next-line identical.alwaysFalse
            if ($historical === UcpVersion::CURRENT) {
                continue;
            }
            $out[$historical] = $baseUri . '/.well-known/ucp/' . $historical;
        }

        return $out;
    }
}

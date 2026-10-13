<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Update\Services;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Update\Struct\SecurityRelease;
use Shopware\Core\Framework\Update\Struct\Version;

/**
 * @phpstan-import-type VersionFixedVulnerabilities from Version
 */
#[Package('framework')]
readonly class SecurityReleaseDetector
{
    /**
     * @var array<string, int>
     */
    private const SEVERITY_RANK = [
        'low' => 1,
        'medium' => 2,
        'high' => 3,
        'critical' => 4,
    ];

    public function detect(Version $version): SecurityRelease
    {
        $securityRelease = new SecurityRelease();
        $securityRelease->version = $version->version;
        $securityRelease->title = $version->title;
        $securityRelease->date = $version->date;
        $securityRelease->vulnerabilities = $version->fixedVulnerabilities;
        $securityRelease->totalVulnerabilities = \count($version->fixedVulnerabilities);

        if ($securityRelease->totalVulnerabilities === 0) {
            if ($this->fallbackIndicatesSecurityRelease($version)) {
                $securityRelease->isSecurityRelease = true;
                $securityRelease->highestSeverity = 'unknown';
            }

            return $securityRelease;
        }

        $securityRelease->isSecurityRelease = true;
        $securityRelease->highestSeverity = $this->determineHighestSeverity($version->fixedVulnerabilities);

        return $securityRelease;
    }

    /**
     * @param VersionFixedVulnerabilities[] $vulnerabilities
     */
    private function determineHighestSeverity(array $vulnerabilities): string
    {
        $highestRank = 0;
        $highestSeverity = 'unknown';

        foreach ($vulnerabilities as $vulnerability) {
            $severity = mb_strtolower($vulnerability['severity'] ?? 'unknown');
            $rank = self::SEVERITY_RANK[$severity] ?? 0;

            if ($rank > $highestRank) {
                $highestRank = $rank;
                $highestSeverity = $severity;
            }
        }

        return $highestSeverity;
    }

    private function fallbackIndicatesSecurityRelease(Version $version): bool
    {
        $haystack = mb_strtolower($version->title . ' ' . $version->body);

        return str_contains($haystack, 'security')
            || str_contains($haystack, 'cve-')
            || str_contains($haystack, 'ghsa-');
    }
}

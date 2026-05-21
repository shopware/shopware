<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Single source of truth for the UCP protocol version Shopware advertises by default.
 *
 * UCP follows a date-based version scheme (YYYY-MM-DD) — see ucp.dev/specification/overview.
 * Bumping CURRENT requires updating: the JSON schema snapshots under Resources/ucp-schemas/,
 * the supported-versions registry, and the conformance test fixtures.
 */
#[Package('framework')]
final class UcpVersion
{
    public const CURRENT = '2026-01-23';

    public const HISTORICAL = [
        '2026-01-11',
    ];

    public const VERSION_PATTERN = '/^\d{4}-\d{2}-\d{2}$/';

    private function __construct()
    {
    }

    public static function isValidFormat(string $version): bool
    {
        return (bool) preg_match(self::VERSION_PATTERN, $version);
    }

    /**
     * Comparable timestamp for a version string. Returns null on invalid input.
     */
    public static function toTimestamp(string $version): ?int
    {
        if (!self::isValidFormat($version)) {
            return null;
        }

        $dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $version);

        return $dt === false ? null : $dt->getTimestamp();
    }

    /**
     * @return -1|0|1
     */
    public static function compare(string $a, string $b): int
    {
        $ta = self::toTimestamp($a) ?? 0;
        $tb = self::toTimestamp($b) ?? 0;

        return $ta <=> $tb;
    }
}

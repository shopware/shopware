<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Generates per-sales-channel cookie names by appending a short hash suffix.
 *
 * When multiple sales channels share the same domain (e.g. "/" and "/cars"),
 * they need isolated cookies so sessions, carts and logins don't leak between them.
 * Language domains of the same sales channel (e.g. "/" and "/de") share the same
 * suffix because they share the same salesChannelId.
 *
 * Example: "session-" becomes "session--a1b2c3d4" for one sales channel,
 * and "session--e5f6g7h8" for another.
 */
#[Package('framework')]
class SalesChannelCookieName
{
    private const SEPARATOR = '--';
    private const SUFFIX_LENGTH = 8;

    /**
     * Build a cookie name with a sales-channel-specific suffix.
     *
     * Returns the original name unchanged when no salesChannelId is provided (BC fallback).
     */
    public static function resolve(string $baseName, ?string $salesChannelId): string
    {
        if ($salesChannelId === null || $salesChannelId === '') {
            return $baseName;
        }

        return rtrim($baseName, '-') . self::SEPARATOR . self::suffix($salesChannelId);
    }

    /**
     * Check whether a cookie name belongs to the given base name,
     * either as an exact match (legacy) or as a suffixed variant.
     */
    public static function matches(string $cookieName, string $baseName): bool
    {
        if ($cookieName === $baseName) {
            return true;
        }

        $prefix = rtrim($baseName, '-') . self::SEPARATOR;

        return str_starts_with($cookieName, $prefix);
    }

    /**
     * Derive a short, deterministic suffix from a sales channel ID.
     */
    public static function suffix(string $salesChannelId): string
    {
        return substr(md5($salesChannelId), 0, self::SUFFIX_LENGTH);
    }
}

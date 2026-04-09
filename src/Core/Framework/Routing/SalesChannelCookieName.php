<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Routing;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\PlatformRequest;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Resolves per-sales-channel session cookie names by appending a short hash suffix.
 *
 * When multiple sales channels share the same domain (e.g. "/" and "/cars"),
 * they need isolated session cookies so sessions, carts and logins don't leak.
 * Language domains of the same sales channel (e.g. "/" and "/de") share the same
 * suffix because they share the same salesChannelId.
 *
 * Example: "session-" becomes "session--a1b2c3d4" for one sales channel
 * and "session--e5f6g7h8" for another.
 */
#[Package('framework')]
class SalesChannelCookieName
{
    private const SEPARATOR = '--';
    private const SUFFIX_LENGTH = 8;

    private readonly string $baseSessionName;

    /**
     * @internal
     *
     * @param array<string, mixed> $sessionOptions
     */
    public function __construct(array $sessionOptions = [])
    {
        $this->baseSessionName = $sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME;
    }

    /**
     * Get the configured base session name (without any suffix).
     */
    public function getBaseName(): string
    {
        return $this->baseSessionName;
    }

    /**
     * Build the session cookie name for a specific sales channel.
     *
     * Returns the base name unchanged when no salesChannelId is provided (BC fallback).
     */
    public function resolve(?string $salesChannelId): string
    {
        if ($salesChannelId === null || $salesChannelId === '') {
            return $this->baseSessionName;
        }

        return rtrim($this->baseSessionName, '-') . self::SEPARATOR . self::suffix($salesChannelId);
    }

    /**
     * Check whether a cookie name is a session cookie — either the base name
     * (legacy/non-storefront) or any per-sales-channel suffixed variant.
     */
    public function matches(string $cookieName): bool
    {
        if ($cookieName === $this->baseSessionName) {
            return true;
        }

        $prefix = rtrim($this->baseSessionName, '-') . self::SEPARATOR;

        return str_starts_with($cookieName, $prefix);
    }

    /**
     * Derive a short, deterministic suffix from a sales channel ID.
     */
    public static function suffix(string $salesChannelId): string
    {
        return substr(Hasher::hash($salesChannelId), 0, self::SUFFIX_LENGTH);
    }
}

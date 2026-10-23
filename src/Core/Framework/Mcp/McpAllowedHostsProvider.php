<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Builds the Host/Origin allowlist for the MCP transport's DnsRebindingProtectionMiddleware.
 *
 * Origins are matched exactly (scheme, host and port): the origins of APP_URL and every sales channel
 * domain, loopback origins for a request to a loopback host, and the origins configured in
 * `shopware.mcp.allowed_origins`. A configured entry is either an exact origin, an origin with `:*`
 * for any port, or `*` for any origin.
 *
 * The MCP SDK ships DNS-rebinding protection whose default allowlist only contains localhost
 * variants. Shopware is reached through its configured public hostnames, so those must be
 * allowlisted too — otherwise every non-local request is rejected with "Invalid Host header".
 * The hostnames are derived from APP_URL and every sales channel domain, keeping the mitigation
 * in place while allowing the shop's own hosts (admin and storefront/store-api).
 */
#[Package('framework')]
class McpAllowedHostsProvider
{
    /**
     * @var list<string>
     */
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    private const ANY_ORIGIN = '*';

    private const ANY_PORT = ':*';

    /**
     * @internal
     *
     * @param list<string> $configuredOrigins
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $appUrl,
        private readonly array $configuredOrigins = [],
    ) {
    }

    /**
     * @return list<string> Lowercased hostnames without port (IPv6 addresses bracketed), as
     *                      expected by {@see \Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware}
     */
    public function getAllowedHosts(): array
    {
        $hosts = self::LOCAL_HOSTS;

        /** @var list<string> $urls */
        $urls = $this->connection->fetchFirstColumn('SELECT `url` FROM `sales_channel_domain`');

        foreach ([$this->appUrl, ...$urls] as $url) {
            $host = $this->extractHost($url);
            if ($host !== null) {
                $hosts[] = $host;
            }
        }

        return array_values(array_unique(array_map('strtolower', $hosts)));
    }

    /**
     * Same-origin requests are checked by the SDK middleware and need no entry here.
     */
    public function isAllowedOrigin(string $origin, string $requestHost): bool
    {
        $normalized = self::normalizeOrigin($origin);
        if ($normalized === null) {
            return false;
        }

        foreach ($this->configuredOrigins as $configured) {
            $configured = strtolower(trim($configured));
            if ($configured === self::ANY_ORIGIN) {
                return true;
            }

            if (str_ends_with($configured, self::ANY_PORT)) {
                if (self::withoutPort($normalized) === self::normalizeOrigin(substr($configured, 0, -\strlen(self::ANY_PORT)))) {
                    return true;
                }

                continue;
            }

            if ($normalized === self::normalizeOrigin($configured)) {
                return true;
            }
        }

        $originHost = parse_url($normalized, \PHP_URL_HOST);
        if (\is_string($originHost) && self::isLoopbackHost($originHost) && self::isLoopbackHost($requestHost)) {
            return true;
        }

        /** @var list<string> $urls */
        $urls = $this->connection->fetchFirstColumn('SELECT `url` FROM `sales_channel_domain`');

        foreach ([$this->appUrl, ...$urls] as $url) {
            if ($normalized === self::normalizeOrigin($url)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Reduces a URL to its lowercased `scheme://host[:port]` origin without default port.
     * Returns null for anything that is not an http(s) URL with a host, such as the opaque origin `null`.
     */
    private static function normalizeOrigin(string $url): ?string
    {
        $parts = parse_url(strtolower(trim($url)));
        if ($parts === false || !isset($parts['scheme'], $parts['host']) || $parts['host'] === '') {
            return null;
        }

        $scheme = $parts['scheme'];
        if ($scheme !== 'http' && $scheme !== 'https') {
            return null;
        }

        $port = $parts['port'] ?? null;
        if (($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80)) {
            $port = null;
        }

        return $scheme . '://' . $parts['host'] . ($port !== null ? ':' . $port : '');
    }

    private static function withoutPort(string $origin): string
    {
        return preg_replace('#:\d+\z#', '', $origin) ?? $origin;
    }

    /**
     * `*.localhost` resolves to loopback (RFC 6761), as do 127.0.0.0/8 and ::1.
     */
    private static function isLoopbackHost(string $host): bool
    {
        $host = strtolower($host);
        if (str_starts_with($host, '[')) {
            return str_starts_with($host, '[::1]');
        }

        $host = explode(':', $host, 2)[0];

        return $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_starts_with($host, '127.');
    }

    private function extractHost(string $url): ?string
    {
        $host = parse_url($url, \PHP_URL_HOST);

        if (!\is_string($host) || $host === '') {
            return null;
        }

        return $host;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Mcp;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Log\Package;

/**
 * @experimental stableVersion:v6.8.0
 *
 * Builds the Host/Origin allowlist for the MCP transport's DnsRebindingProtectionMiddleware.
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

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly string $appUrl,
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

    private function extractHost(string $url): ?string
    {
        $host = parse_url($url, \PHP_URL_HOST);

        if (!\is_string($host) || $host === '') {
            return null;
        }

        return $host;
    }
}

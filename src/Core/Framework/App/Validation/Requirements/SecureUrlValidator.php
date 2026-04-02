<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Validation\Requirements;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 *
 * Validates whether a given shop URL is a secure and valid target.
 *
 * The validator ensures that the URL: (this remains a simple validation and does not guarantee absolute correctness)
 *   - Uses the HTTPS scheme
 *   - Does not use an IP address literal as the host
 *   - Is not 'localhost' or a reserved domain
 *   - Resolves (via DNS) to a public, non-reserved IP address
 *
 * @see https://www.iana.org/assignments/special-use-domain-names/special-use-domain-names.xhtml
 */
#[Package('framework')]
readonly class SecureUrlValidator
{
    private const RESERVED_SUFFIXES = [
        '.localhost',
        '.local',
        '.test',
        '.example',
        '.invalid',
        '.onion',
        '.home.arpa',
    ];

    private const RESERVED_EXACT = [
        'example.com',
        'example.net',
        'example.org',
        'home.arpa',
        'localdomain',
    ];

    /**
     * @var \Closure(string): list<array{ip?: string, ipv6?: string}>
     */
    private \Closure $dnsResolver;

    /**
     * @param (\Closure(string): list<array{ip?: string, ipv6?: string}>)|null $dnsResolver
     */
    public function __construct(?\Closure $dnsResolver = null)
    {
        $this->dnsResolver = $dnsResolver ?? static function (string $host): array {
            $records = @dns_get_record($host, \DNS_A | \DNS_AAAA);

            return \is_array($records) ? $records : [];
        };
    }

    public function isValidTarget(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);
        if (!\is_string($host)) {
            return false;
        }

        $host = rtrim($host, '.');

        if (!$this->hasHttpsScheme($url)) {
            return false;
        }

        if ($this->isIpAddress($host)) {
            return false;
        }

        if ($this->isReserved($host)) {
            return false;
        }

        if (!$this->resolvesToPublicAddress($host)) {
            return false;
        }

        return true;
    }

    private function hasHttpsScheme(string $url): bool
    {
        return parse_url($url, \PHP_URL_SCHEME) === 'https';
    }

    private function isIpAddress(string $host): bool
    {
        $cleanHost = trim($host, '[]');

        return filter_var($cleanHost, \FILTER_VALIDATE_IP) !== false;
    }

    private function resolvesToPublicAddress(string $host): bool
    {
        $records = ($this->dnsResolver)($host);
        if ($records === []) {
            return false;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip === null) {
                continue;
            }

            if (filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    private function isReserved(string $host): bool
    {
        $host = strtolower($host);

        if ($host === 'localhost' || \in_array($host, self::RESERVED_EXACT, true)) {
            return true;
        }

        $dotHost = '.' . ltrim($host, '.');

        return array_any(
            self::RESERVED_SUFFIXES,
            static fn (string $suffix): bool => str_ends_with($dotHost, $suffix)
        );
    }
}

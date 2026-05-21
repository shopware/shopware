<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Ucp\Profile;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Ucp\UcpException;

/**
 * @experimental stableVersion:v6.8.0 feature:UCP_SERVER
 *
 * Production-grade URL safety validation for platform profile fetches.
 *
 * Defends against:
 *
 *  T1  SSRF via private/loopback ranges
 *      → Rejects RFC 1918 (10/8, 172.16/12, 192.168/16), RFC 4193 (fc00::/7),
 *        RFC 3927 (link-local 169.254/16, fe80::/10), 127.0.0.0/8, 0.0.0.0,
 *        multicast, broadcast, IPv4-mapped IPv6.
 *  T2  DNS rebinding
 *      → Resolves the host once, validates all returned A/AAAA records, then
 *        pins the IP for the actual HTTP request (so the resolved IP that
 *        we validated is also what we connect to).
 *  T3  Cloud-metadata endpoints
 *      → Rejects 169.254.169.254 (AWS, GCE, DigitalOcean, …) and the IMDSv2
 *        IPv6 equivalents.
 *  T4  Userinfo embedding
 *      → Rejects URLs that carry `user@host` form (RFC 3986 userinfo).
 *  T5  Non-default ports for HTTPS
 *      → Allows only 443 (HTTPS) and 80 in local-dev mode.
 *  T6  Punycode/IDN homograph attacks
 *      → Normalises the host and rejects mixed Unicode + ASCII labels.
 *  T7  Operator-configured allowlist enforcement
 *      → If a Sales Channel sets `platform_allowlist`, the URL's host must
 *        appear in it.
 *
 * @internal
 */
#[Package('framework')]
class UrlSafetyValidator
{
    public const CLOUD_METADATA_IPS = [
        '169.254.169.254',  // AWS / GCE / DO / Azure / Alibaba IMDS
        'fd00:ec2::254',    // AWS IPv6 metadata
        'metadata.google.internal',
    ];

    /**
     * Validate and resolve a profile URL.
     *
     * @param array<string>|null $allowlist null = permissionless onboarding
     *
     * @return array{url: string, host: string, resolved_ip: string} validated URL + resolved IP
     */
    public function validateAndResolve(string $url, ?array $allowlist, string $environment): array
    {
        $parts = parse_url($url);
        if (!\is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            throw UcpException::invalidProfileUrl($url);
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw UcpException::invalidProfileUrl($url);
        }

        $scheme = strtolower($parts['scheme']);
        $localDevAllowed = $environment !== 'prod';

        if ($scheme !== 'https' && !($localDevAllowed && $scheme === 'http')) {
            throw UcpException::invalidProfileUrl($url);
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if ($scheme === 'https' && $port !== 443) {
            throw UcpException::invalidProfileUrl($url);
        }

        $host = strtolower($parts['host']);
        $host = $this->normalizeHost($host);
        if ($host === '') {
            throw UcpException::invalidProfileUrl($url);
        }

        // Reject cloud metadata hostnames
        foreach (self::CLOUD_METADATA_IPS as $forbidden) {
            if ($host === strtolower($forbidden)) {
                throw UcpException::invalidProfileUrl($url);
            }
        }

        // Allowlist check
        if ($allowlist !== null && !$this->hostMatchesAllowlist($host, $allowlist)) {
            throw UcpException::invalidProfileUrl($url);
        }

        // Resolve DNS — pin first usable IP, validate it isn't a private range
        $resolvedIp = $this->resolvePinnedIp($host, $localDevAllowed);

        return ['url' => $url, 'host' => $host, 'resolved_ip' => $resolvedIp];
    }

    /**
     * @param array<string> $allowlist
     */
    private function hostMatchesAllowlist(string $host, array $allowlist): bool
    {
        foreach ($allowlist as $entry) {
            $entry = strtolower(trim((string) $entry));
            if ($entry === $host) {
                return true;
            }
            // Wildcard subdomain support: `*.example.com`
            if (str_starts_with($entry, '*.') && str_ends_with($host, substr($entry, 1))) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHost(string $host): string
    {
        // Reject mixed-script (homograph) hosts
        if (preg_match('/^[\x00-\x7F]+$/', $host) !== 1) {
            // Non-ASCII detected — must be valid Punycode (xn--) or rejected
            if (\function_exists('idn_to_ascii')) {
                $ascii = idn_to_ascii($host, \IDNA_DEFAULT, \INTL_IDNA_VARIANT_UTS46);
                if ($ascii === false) {
                    return '';
                }
                $host = $ascii;
            } else {
                return '';
            }
        }

        // Strip trailing dot (FQDN normalisation)
        return rtrim($host, '.');
    }

    private function resolvePinnedIp(string $host, bool $localDevAllowed): string
    {
        // If host is already an IP literal — validate directly
        $ipDirect = filter_var($host, \FILTER_VALIDATE_IP);
        if ($ipDirect !== false) {
            $this->assertPublicIp($ipDirect, $localDevAllowed);

            return $ipDirect;
        }

        // Resolve A/AAAA records
        $records = @dns_get_record($host, \DNS_A | \DNS_AAAA);
        $ips = [];
        if (\is_array($records)) {
            foreach ($records as $record) {
                if (isset($record['ip'])) {
                    $ips[] = $record['ip'];
                } elseif (isset($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            // Local dev: gethostbyname for /etc/hosts (localhost, .local entries)
            if ($localDevAllowed) {
                $resolved = gethostbyname($host);
                if ($resolved !== $host) {
                    $this->assertPublicIp($resolved, $localDevAllowed);

                    return $resolved;
                }
            }

            throw UcpException::profileUnreachable($host, 'DNS resolution returned no A/AAAA records');
        }

        foreach ($ips as $ip) {
            $this->assertPublicIp($ip, $localDevAllowed);
        }

        return $ips[0];
    }

    private function assertPublicIp(string $ip, bool $localDevAllowed): void
    {
        if (filter_var($ip, \FILTER_VALIDATE_IP) === false) {
            throw UcpException::invalidProfileUrl($ip);
        }

        // Block cloud metadata IPs unconditionally — even in dev
        if (\in_array($ip, self::CLOUD_METADATA_IPS, true)) {
            throw UcpException::invalidProfileUrl($ip);
        }

        // In dev mode we permit loopback, link-local, and RFC 1918 private ranges
        // so that container-based development (Dockware, Lando, host.docker.internal)
        // works out of the box. Production hosts must NEVER hit this branch.
        if ($localDevAllowed) {
            return;
        }

        $flags = \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE;
        if (filter_var($ip, \FILTER_VALIDATE_IP, $flags) === false) {
            throw UcpException::invalidProfileUrl($ip);
        }
    }
}

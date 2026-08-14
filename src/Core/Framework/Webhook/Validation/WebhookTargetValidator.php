<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Validation;

use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WebhookTargetValidator
{
    /**
     * @var list<string>
     */
    private const NON_GLOBAL_IP_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.88.99.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/96',
        '::1/128',
        '::ffff:0:0/96',
        '64:ff9b::/96',
        '64:ff9b:1::/48',
        '100::/64',
        '100:0:0:1::/64',
        '2001::/23',
        '2001:2::/48',
        '2001:db8::/32',
        '2002::/16',
        '3fff::/20',
        '5f00::/16',
        'fc00::/7',
        'fe80::/10',
        'fec0::/10',
        'ff00::/8',
    ];

    /**
     * @var list<string>
     */
    private array $allowedIpAddresses;

    /**
     * @var \Closure(string): list<array{ip?: string, ipv6?: string}>
     */
    private \Closure $dnsResolver;

    /**
     * @param list<string> $allowedIpAddresses
     * @param (\Closure(string): list<array{ip?: string, ipv6?: string}>)|null $dnsResolver
     */
    public function __construct(
        private bool $allowUnencryptedTraffic,
        array $allowedIpAddresses = [],
        ?\Closure $dnsResolver = null,
        private bool $allowPublicIpLiterals = false,
    ) {
        $this->allowedIpAddresses = array_values(array_filter(
            $allowedIpAddresses,
            static fn (string $ip): bool => filter_var($ip, \FILTER_VALIDATE_IP) !== false
        ));

        $this->dnsResolver = $dnsResolver ?? self::createDefaultDnsResolver();
    }

    public function validate(string $url): ?WebhookTarget
    {
        $scheme = parse_url($url, \PHP_URL_SCHEME);
        if (!\is_string($scheme) || !$this->isAllowedScheme($scheme)) {
            return null;
        }

        $host = parse_url($url, \PHP_URL_HOST);
        if (!\is_string($host) || $host === '') {
            return null;
        }

        $host = rtrim(strtolower($host), '.');
        $port = parse_url($url, \PHP_URL_PORT);
        if (!\is_int($port)) {
            $port = $scheme === 'http' ? 80 : 443;
        }

        $ipLiteral = trim($host, '[]');
        if (filter_var($ipLiteral, \FILTER_VALIDATE_IP) !== false) {
            return ($this->allowPublicIpLiterals && $this->isPublicIpAddress($ipLiteral)) || $this->isAllowedIpAddress($ipLiteral)
                ? new WebhookTarget($host, $port, $ipLiteral)
                : null;
        }

        $records = ($this->dnsResolver)($host);
        if ($records === []) {
            return null;
        }

        $validatedIp = null;
        foreach ($records as $record) {
            $ip = $record['ip'] ?? $record['ipv6'] ?? null;
            if ($ip === null) {
                continue;
            }

            if (!$this->isPublicIpAddress($ip) && !$this->isAllowedIpAddress($ip)) {
                return null;
            }

            $validatedIp ??= $ip;
        }

        if ($validatedIp === null) {
            return null;
        }

        return new WebhookTarget($host, $port, $validatedIp);
    }

    private function isAllowedScheme(string $scheme): bool
    {
        return $scheme === 'https' || ($this->allowUnencryptedTraffic && $scheme === 'http');
    }

    private function isPublicIpAddress(string $ip): bool
    {
        if (filter_var($ip, \FILTER_VALIDATE_IP) === false) {
            return false;
        }

        foreach (self::NON_GLOBAL_IP_RANGES as $range) {
            if ($this->isIpAddressInRange($ip, $range)) {
                return false;
            }
        }

        return true;
    }

    private function isAllowedIpAddress(string $ip): bool
    {
        return \in_array($ip, $this->allowedIpAddresses, true);
    }

    private function isIpAddressInRange(string $ip, string $range): bool
    {
        [$network, $prefixLength] = explode('/', $range, 2);
        $packedIp = inet_pton($ip);
        $packedNetwork = inet_pton($network);

        if ($packedIp === false || $packedNetwork === false || \strlen($packedIp) !== \strlen($packedNetwork)) {
            return false;
        }

        $prefixLength = (int) $prefixLength;
        $fullBytes = intdiv($prefixLength, 8);
        $remainingBits = $prefixLength % 8;

        if (substr($packedIp, 0, $fullBytes) !== substr($packedNetwork, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = 0xFF << (8 - $remainingBits);

        return (\ord($packedIp[$fullBytes]) & $mask) === (\ord($packedNetwork[$fullBytes]) & $mask);
    }

    /**
     * @return \Closure(string): list<array{ip?: string, ipv6?: string}>
     */
    private static function createDefaultDnsResolver(): \Closure
    {
        return static function (string $host): array {
            $records = @dns_get_record($host, \DNS_A | \DNS_AAAA);
            if (!\is_array($records)) {
                return [];
            }

            $addresses = [];
            foreach ($records as $record) {
                if (isset($record['ip']) && \is_string($record['ip'])) {
                    $addresses[] = ['ip' => $record['ip']];
                }

                if (isset($record['ipv6']) && \is_string($record['ipv6'])) {
                    $addresses[] = ['ipv6' => $record['ipv6']];
                }
            }

            return $addresses;
        };
    }
}

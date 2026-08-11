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
            return $this->isAllowedIpAddress($ipLiteral) ? new WebhookTarget($host, $port, $ipLiteral) : null;
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
        return filter_var($ip, \FILTER_VALIDATE_IP, \FILTER_FLAG_NO_PRIV_RANGE | \FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    private function isAllowedIpAddress(string $ip): bool
    {
        return \in_array($ip, $this->allowedIpAddresses, true);
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

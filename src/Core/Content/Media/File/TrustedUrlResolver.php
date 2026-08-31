<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\File;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('discovery')]
class TrustedUrlResolver implements ResetInterface
{
    public const BLOCKED_SUBNETS = [...IpUtils::PRIVATE_SUBNETS, '192.0.0.0/24'];

    /**
     * @var \Closure(string): list<string>
     */
    private readonly \Closure $dnsResolver;

    /**
     * @var array<string, list<string>>
     */
    private array $resolved = [];

    /**
     * @param (\Closure(string): list<string>)|null $dnsResolver
     */
    public function __construct(
        ?\Closure $dnsResolver = null,
        private readonly bool $rejectPrivateRanges = true,
    ) {
        $this->dnsResolver = $dnsResolver ?? static function (string $host): array {
            $ips = [];

            $records = @dns_get_record($host, \DNS_A | \DNS_AAAA);
            if (\is_array($records)) {
                foreach ($records as $record) {
                    $ip = $record['ip'] ?? $record['ipv6'] ?? null;
                    if (\is_string($ip) && $ip !== '') {
                        $ips[] = $ip;
                    }
                }
            }

            if ($ips === []) {
                // gethostbyname() reads the hosts file, dns_get_record() does not
                $ip = @gethostbyname($host);
                if ($ip !== $host && filter_var($ip, \FILTER_VALIDATE_IP) !== false) {
                    $ips[] = $ip;
                }
            }

            return $ips;
        };
    }

    /**
     * @throws MediaException
     */
    public function resolve(string $url): ResolvedUrl
    {
        $scheme = parse_url($url, \PHP_URL_SCHEME);
        if ($scheme !== 'http' && $scheme !== 'https') {
            throw MediaException::illegalUrl($url);
        }

        $host = parse_url($url, \PHP_URL_HOST);
        if (!\is_string($host) || $host === '') {
            throw MediaException::illegalUrl($url);
        }

        $host = trim($host, '[]');

        $addresses = $this->resolveAddresses($host);
        if ($addresses === []) {
            throw MediaException::illegalUrl($url);
        }

        if ($this->rejectPrivateRanges) {
            foreach ($addresses as $ip) {
                if (IpUtils::checkIp($ip, self::BLOCKED_SUBNETS)) {
                    throw MediaException::illegalUrl($url);
                }
            }
        }

        return new ResolvedUrl($host, $addresses[0]);
    }

    public function isValid(string $url): bool
    {
        try {
            $this->resolve($url);
        } catch (MediaException) {
            return false;
        }

        return true;
    }

    public function reset(): void
    {
        $this->resolved = [];
    }

    /**
     * @return list<string>
     */
    private function resolveAddresses(string $host): array
    {
        if (filter_var($host, \FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        if (isset($this->resolved[$host])) {
            return $this->resolved[$host];
        }

        $addresses = [];
        foreach (($this->dnsResolver)($host) as $ip) {
            if (filter_var($ip, \FILTER_VALIDATE_IP) !== false) {
                $addresses[] = $ip;
            }
        }

        return $this->resolved[$host] = $addresses;
    }
}

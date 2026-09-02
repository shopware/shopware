<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Validation;

use Shopware\Core\Content\Media\File\TrustedUrlResolver;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * @internal
 */
#[Package('framework')]
final readonly class WebhookTargetValidator
{
    /**
     * @var list<string>
     */
    private array $allowedPrivateIpAddresses;

    private TrustedUrlResolver $urlResolver;

    /**
     * @param list<string> $allowedPrivateIpAddresses List of IP addresses or CIDR ranges (e.g. "10.0.0.0/8")
     */
    public function __construct(
        private bool $allowUnencryptedTraffic,
        array $allowedPrivateIpAddresses = [],
        ?TrustedUrlResolver $urlResolver = null,
    ) {
        $this->allowedPrivateIpAddresses = array_values(array_filter(
            $allowedPrivateIpAddresses,
            static fn (string $ip): bool => self::isValidIpOrCidr($ip)
        ));
        $this->urlResolver = $urlResolver ?? new TrustedUrlResolver(allowedPrivateIps: $this->allowedPrivateIpAddresses);
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
        if (filter_var($ipLiteral, \FILTER_VALIDATE_IP) !== false && !IpUtils::checkIp($ipLiteral, $this->allowedPrivateIpAddresses)) {
            return null;
        }

        try {
            $resolvedUrl = $this->urlResolver->resolve($url);
        } catch (MediaException) {
            return null;
        }

        return new WebhookTarget($host, $port, $resolvedUrl->ip);
    }

    private function isAllowedScheme(string $scheme): bool
    {
        return $scheme === 'https' || ($this->allowUnencryptedTraffic && $scheme === 'http');
    }

    private static function isValidIpOrCidr(string $value): bool
    {
        if (filter_var($value, \FILTER_VALIDATE_IP) !== false) {
            return true;
        }

        if (!str_contains($value, '/')) {
            return false;
        }

        [$subnet, $prefix] = explode('/', $value, 2);

        if (filter_var($subnet, \FILTER_VALIDATE_IP) === false || !ctype_digit($prefix)) {
            return false;
        }

        $maxPrefix = filter_var($subnet, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) !== false ? 128 : 32;

        return (int) $prefix <= $maxPrefix;
    }
}

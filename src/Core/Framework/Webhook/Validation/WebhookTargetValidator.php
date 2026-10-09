<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Webhook\Validation;

use Shopware\Core\Content\Media\File\TrustedUrlResolver;
use Shopware\Core\Content\Media\MediaException;
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
    private array $allowedPrivateIpAddresses;

    private TrustedUrlResolver $urlResolver;

    /**
     * @param list<string> $allowedPrivateIpAddresses
     */
    public function __construct(
        private bool $allowUnencryptedTraffic,
        array $allowedPrivateIpAddresses = [],
        ?TrustedUrlResolver $urlResolver = null,
        private bool $enableUrlValidation = true,
    ) {
        $this->allowedPrivateIpAddresses = array_values(array_filter(
            $allowedPrivateIpAddresses,
            static fn (string $ip): bool => filter_var($ip, \FILTER_VALIDATE_IP) !== false
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

        if (!$this->enableUrlValidation) {
            return new WebhookTarget($host, $port, null);
        }

        $ipLiteral = trim($host, '[]');
        if (filter_var($ipLiteral, \FILTER_VALIDATE_IP) !== false && !\in_array($ipLiteral, $this->allowedPrivateIpAddresses, true)) {
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
        if ($scheme === 'https') {
            return true;
        }

        return $scheme === 'http' && ($this->allowUnencryptedTraffic || !$this->enableUrlValidation);
    }
}

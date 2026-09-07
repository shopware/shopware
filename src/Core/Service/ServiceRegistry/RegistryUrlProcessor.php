<?php declare(strict_types=1);

namespace Shopware\Core\Service\ServiceRegistry;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\EnvVarProcessorInterface;

/**
 * Resolves the configured registry URL and replaces it with the built-in one when it does not belong to a
 * trusted domain. The registry decides which services a shop installs and where their code is downloaded
 * from, so a mistyped or manipulated URL must not be able to point a live shop at a foreign registry.
 *
 * The value is resolved at runtime, so a container built without `SERVICE_REGISTRY_URL` still picks up the
 * variable when it is injected into the environment later.
 *
 * @internal
 */
#[Package('framework')]
class RegistryUrlProcessor implements EnvVarProcessorInterface
{
    /**
     * @param list<string> $trustedDomains the URL host must be one of these domains or a subdomain of one.
     *                                     An empty list accepts every URL, which is how non-production
     *                                     environments point at a local registry.
     */
    public function __construct(
        private readonly string $defaultUrl,
        private readonly array $trustedDomains,
    ) {
    }

    public function getEnv(string $prefix, string $name, \Closure $getEnv): string
    {
        $url = (string) $getEnv($name);

        if ($this->trustedDomains === [] || $this->isTrusted($url)) {
            return $url;
        }

        return $this->defaultUrl;
    }

    public static function getProvidedTypes(): array
    {
        return [
            'service-registry-url' => 'string',
        ];
    }

    private function isTrusted(string $url): bool
    {
        $host = parse_url($url, \PHP_URL_HOST);

        if (!\is_string($host)) {
            return false;
        }

        // a trailing dot denotes the same host, `parse_url()` keeps the casing of the configured value
        $host = rtrim(mb_strtolower($host), '.');

        foreach ($this->trustedDomains as $domain) {
            if ($host === $domain || str_ends_with($host, '.' . $domain)) {
                return true;
            }
        }

        return false;
    }
}

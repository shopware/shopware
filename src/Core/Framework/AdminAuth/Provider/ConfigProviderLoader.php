<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Provider;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Builds {@see AdminAuthProvider} structs from the static `shopware.admin_auth.providers` YAML
 * configuration.
 *
 * Provider ids must be stable across requests and deployments (they are baked into the authorize
 * redirect URLs registered at the identity provider), so they are derived deterministically from
 * the provider's configuration name. Discovery is NOT resolved here: endpoints stay null until the
 * OIDC layer resolves them lazily on first use.
 *
 * @internal
 */
#[Package('framework')]
class ConfigProviderLoader
{
    final public const PROVIDER_KEY_PREFIX = 'yaml:';

    private const ID_NAMESPACE = 'shopware-admin-auth-yaml-provider:';

    /**
     * @param array<string, array<string, mixed>> $providerConfig the processed `shopware.admin_auth.providers` configuration
     */
    public function __construct(private readonly array $providerConfig = [])
    {
    }

    public function hasProviders(): bool
    {
        return $this->providerConfig !== [];
    }

    /**
     * @return list<AdminAuthProvider>
     */
    public function load(): array
    {
        $providers = [];

        foreach ($this->providerConfig as $name => $config) {
            $name = (string) $name;

            $providers[] = new AdminAuthProvider(
                id: self::deterministicId($name),
                providerKey: self::PROVIDER_KEY_PREFIX . $name,
                label: $this->string($config['label'] ?? null) ?? $name,
                clientId: $this->string($config['client_id'] ?? null) ?? '',
                clientSecret: $this->string($config['client_secret'] ?? null) ?? '',
                discoveryUrl: $this->string($config['discovery_url'] ?? null),
                issuer: $this->string($config['issuer'] ?? null),
                authorizationEndpoint: $this->string($config['authorization_endpoint'] ?? null),
                tokenEndpoint: $this->string($config['token_endpoint'] ?? null),
                jwksUri: $this->string($config['jwks_uri'] ?? null),
                scopes: $this->stringList($config['scopes'] ?? null) ?: ['openid', 'profile', 'email'],
                autoProvision: (bool) ($config['auto_provision'] ?? false),
                groupsClaim: $this->string($config['groups_claim'] ?? null),
                roleMapping: $this->roleMapping($config['role_mapping'] ?? null),
                defaultRoles: $this->stringList($config['default_roles'] ?? null),
                active: true,
            );
        }

        return $providers;
    }

    /**
     * Deterministic, name-derived UUID: md5-based like {@see Uuid::fromStringToHex} with the
     * version nibble forced to '5' to make the name-based derivation explicit and keep the ids
     * distinguishable from random v4 UUIDs of database providers.
     */
    public static function deterministicId(string $name): string
    {
        $hex = Uuid::fromStringToHex(self::ID_NAMESPACE . $name);
        $hex[12] = '5';

        return $hex;
    }

    private function string(mixed $value): ?string
    {
        return \is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            fn (mixed $item): ?string => $this->string($item),
            $value
        )));
    }

    /**
     * @return array<string, list<string>>
     */
    private function roleMapping(mixed $value): array
    {
        if (!\is_array($value)) {
            return [];
        }

        $mapping = [];
        foreach ($value as $group => $roles) {
            $mapping[(string) $group] = $this->stringList($roles);
        }

        return $mapping;
    }
}

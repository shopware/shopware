<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Provider;

use Shopware\Core\Framework\Log\Package;

/**
 * Runtime representation of a configured OIDC login provider, decoupled from its source:
 * providers can come from the `admin_auth_provider` table (managed via the admin UI) or from the
 * static `shopware.admin_auth.providers` YAML configuration.
 *
 * `id` is always a 32-char hex UUID (deterministically derived for YAML providers, so authorize
 * redirect URLs stay stable across deployments). `providerKey` identifies the configuration source
 * (`yaml:<name>` for YAML providers, the hex id for database providers) and is used wherever a
 * stable reference to the provider must survive deletion/re-creation, e.g. role-sync tracking.
 *
 * @experimental stableVersion:v6.9.0 feature:ADMIN_AUTH
 */
#[Package('framework')]
final class AdminAuthProvider
{
    /**
     * @param list<string> $scopes OAuth2 scopes requested from the identity provider
     * @param array<string, list<string>> $roleMapping IdP group => acl role names ('admin' = pseudo-role for the user admin flag)
     * @param list<string> $defaultRoles acl role names granted to every user of this provider
     */
    public function __construct(
        public readonly string $id,
        public readonly string $providerKey,
        public readonly string $label,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly ?string $discoveryUrl = null,
        public readonly ?string $issuer = null,
        public readonly ?string $authorizationEndpoint = null,
        public readonly ?string $tokenEndpoint = null,
        public readonly ?string $jwksUri = null,
        public readonly array $scopes = ['openid', 'profile', 'email'],
        public readonly bool $autoProvision = false,
        public readonly ?string $groupsClaim = null,
        public readonly array $roleMapping = [],
        public readonly array $defaultRoles = [],
        public readonly bool $active = true,
    ) {
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\AdminAuth\Provider;

use Shopware\Core\Framework\Log\Package;

/**
 * Single lookup point for OIDC login providers.
 *
 * Precedence: as soon as providers are declared in the YAML configuration
 * (`shopware.admin_auth.providers`), ONLY those are used and database providers are ignored — the
 * deployment explicitly opted into configuration-managed providers, and mixing both sources would
 * make the admin UI lie about what is active. Without YAML providers, the database providers
 * managed via the admin UI apply.
 *
 * @internal
 */
#[Package('framework')]
class ProviderRegistry
{
    public function __construct(
        private readonly ConfigProviderLoader $configLoader,
        private readonly DalProviderLoader $dalLoader,
        private readonly bool $adminUi = true,
    ) {
    }

    /**
     * @return list<AdminAuthProvider> all active providers
     */
    public function all(): array
    {
        return array_values(array_filter(
            $this->load(),
            static fn (AdminAuthProvider $provider): bool => $provider->active
        ));
    }

    public function byId(string $id): ?AdminAuthProvider
    {
        $id = strtolower($id);

        foreach ($this->load() as $provider) {
            if ($provider->id === $id) {
                return $provider;
            }
        }

        return null;
    }

    /**
     * Whether providers are declared in the YAML configuration (which takes precedence over the
     * database and locks the admin UI).
     */
    public function isManagedByConfig(): bool
    {
        return $this->configLoader->hasProviders();
    }

    /**
     * Whether providers may be managed through the administration (`shopware.admin_auth.admin_ui`
     * enabled and no YAML providers configured).
     */
    public function isAdminUiEnabled(): bool
    {
        return $this->adminUi && !$this->isManagedByConfig();
    }

    /**
     * @return list<AdminAuthProvider>
     */
    private function load(): array
    {
        return $this->isManagedByConfig() ? $this->configLoader->load() : $this->dalLoader->load();
    }
}

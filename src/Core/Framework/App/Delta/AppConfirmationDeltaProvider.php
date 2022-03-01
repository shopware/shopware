<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Delta;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\App\Manifest\Manifest;

/**
 * @internal only for use by the app-system
 */
class AppConfirmationDeltaProvider
{
    /**
     * @var AbstractAppDeltaProvider[]
     */
    private iterable $deltaProviders;

    public function __construct(iterable $providers)
    {
        $this->deltaProviders = $providers;
    }

    /**
     * @return array<string, array>
     */
    public function getDeltas(Manifest $manifest, AppEntity $app): array
    {
        $deltas = [];

        foreach ($this->deltaProviders as $provider) {
            $deltas[$provider->getDeltaName()] = $provider->getDelta($manifest, $app);
        }

        return $deltas;
    }

    public function requiresRenewedConsent(Manifest $manifest, AppEntity $app): bool
    {
        foreach ($this->deltaProviders as $provider) {
            if ($provider->hasDelta($manifest, $app)) {
                return true;
            }
        }

        return false;
    }
}

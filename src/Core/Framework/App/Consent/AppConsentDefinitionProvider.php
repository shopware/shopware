<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Consent;

use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinitionProvider;

/**
 * Hands the consents declared by active apps to the core consent registry.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
class AppConsentDefinitionProvider implements ConsentDefinitionProvider
{
    public function __construct(private readonly AppFeatureStorage $storage)
    {
    }

    public function getConsentDefinitions(): array
    {
        $definitions = [];

        foreach ($this->storage->forActiveApps(ConsentConfig::class) as $feature) {
            $definitions[] = new AppConsentDefinition($feature->appName, $feature->config, $feature->createdAt);
        }

        return $definitions;
    }
}

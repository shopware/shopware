<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Consent;

use Shopware\Core\Framework\App\Feature\AppFeatureStorage;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Consent\ConsentDefinitionProvider;

/**
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
            $definitions[] = new AppConsentDefinition($feature->appName, $feature->config);
        }

        return $definitions;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescriber;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescription;
use Shopware\Core\Service\Event\CommercialLicenseProvidedEvent;

/**
 * @internal only for use by the app-system
 */
#[Package('framework')]
class ServiceHookableEventDescriber implements HookableEventDescriber
{
    public function describe(): array
    {
        return [];
    }

    public function describeForValidation(Manifest $manifest): array
    {
        if (!$manifest->getMetadata()->isSelfManaged()) {
            return [];
        }

        return $this->getServiceEventDescriptions();
    }

    /**
     * @return list<HookableEventDescription>
     */
    private function getServiceEventDescriptions(): array
    {
        return [
            new HookableEventDescription(
                CommercialLicenseProvidedEvent::NAME,
                'Fires when the current commercial license data is provided to services.',
                []
            ),
        ];
    }
}

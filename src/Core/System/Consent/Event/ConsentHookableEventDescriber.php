<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Event;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\App\Consent\AppConsentDefinition;
use Shopware\Core\Framework\App\Consent\ConsentFeatureDefinition;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescriber;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescription;
use Shopware\Core\System\Consent\ConsentDefinition;
use Shopware\Core\System\Consent\Service\ConsentService;

#[Package('data-services')]
class ConsentHookableEventDescriber implements HookableEventDescriber
{
    /**
     * @internal
     */
    public function __construct(
        private readonly ConsentService $consentService,
        private readonly ConsentFeatureDefinition $consentFeatureDefinition,
    ) {
    }

    /**
     * @return list<HookableEventDescription>
     */
    public function describe(): array
    {
        return $this->getDescriptions($this->consentService->definitions());
    }

    public function describeForValidation(Manifest $manifest): array
    {
        $definitions = $this->consentService->definitions();

        foreach ($this->consentFeatureDefinition->fromManifest($manifest) as $config) {
            $definition = new AppConsentDefinition($manifest->getMetadata()->getName(), $config);
            $definitions[$definition->getName()] = $definition;
        }

        return $this->getDescriptions($definitions);
    }

    /**
     * @param array<string, ConsentDefinition> $definitions
     *
     * @return list<HookableEventDescription>
     */
    private function getDescriptions(array $definitions): array
    {
        $events = [];

        foreach ($definitions as $consentDefinition) {
            $consentName = $consentDefinition->getName();
            $privilege = \sprintf('consent:%s:%s', $consentName, AclRoleDefinition::PRIVILEGE_READ);

            $events[] = new HookableEventDescription(
                \sprintf('consent.%s.accepted', $consentName),
                \sprintf('Fires when the %s consent is accepted.', $consentName),
                [$privilege]
            );
            $events[] = new HookableEventDescription(
                \sprintf('consent.%s.revoked', $consentName),
                \sprintf('Fires when the %s consent is revoked.', $consentName),
                [$privilege]
            );
        }

        return $events;
    }
}

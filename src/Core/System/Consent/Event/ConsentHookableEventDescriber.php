<?php declare(strict_types=1);

namespace Shopware\Core\System\Consent\Event;

use Shopware\Core\Framework\Api\Acl\Role\AclRoleDefinition;
use Shopware\Core\Framework\App\Manifest\Manifest;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescriber;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescription;
use Shopware\Core\System\Consent\ConsentDefinitionRegistry;
use Shopware\Core\System\Consent\ConsentScope\StorefrontVisitor;

#[Package('data-services')]
class ConsentHookableEventDescriber implements HookableEventDescriber
{
    /**
     * @internal
     */
    public function __construct(private readonly ConsentDefinitionRegistry $consentDefinitionRegistry)
    {
    }

    /**
     * @return list<HookableEventDescription>
     */
    public function describe(): array
    {
        return $this->getDescriptions();
    }

    public function describeForValidation(Manifest $manifest): array
    {
        return $this->getDescriptions();
    }

    /**
     * @return list<HookableEventDescription>
     */
    private function getDescriptions(): array
    {
        $events = [];

        foreach ($this->consentDefinitionRegistry->all() as $consentDefinition) {
            // Storefront visitor consents cannot be accepted or revoked through the
            // admin-only consent endpoints, so their accepted/revoked events never
            // fire; advertising them would offer apps webhooks that never trigger.
            // Revisit if consents ever become manageable via the Store API.
            if ($consentDefinition->getScopeName() === StorefrontVisitor::NAME) {
                continue;
            }

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

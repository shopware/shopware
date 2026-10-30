<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Log\Package;

/**
 * The ACL roles of users and integrations. A user and an integration may share an id, so they are kept apart.
 *
 * @internal
 */
#[Package('framework')]
final readonly class IdentityRoleMap
{
    /**
     * @param array<string, AclRoles> $userRoles keyed by user id
     * @param array<string, AclRoles> $integrationRoles keyed by integration id
     * @param list<string> $appIntegrationIds
     */
    public function __construct(
        private array $userRoles,
        private array $integrationRoles,
        private array $appIntegrationIds,
    ) {
    }

    /**
     * The roles of the given identity, or null if it is unknown. Integration finds every integration, App only the
     * integration of an app.
     */
    public function rolesFor(string $id, IdentityType $type): ?AclRoles
    {
        return match ($type) {
            IdentityType::User => $this->userRoles[$id] ?? null,
            IdentityType::Integration => $this->integrationRoles[$id] ?? null,
            IdentityType::App => \in_array($id, $this->appIntegrationIds, true) ? $this->integrationRoles[$id] ?? null : null,
        };
    }

    public function isType(string $id, IdentityType $type): bool
    {
        return $this->rolesFor($id, $type) !== null;
    }
}

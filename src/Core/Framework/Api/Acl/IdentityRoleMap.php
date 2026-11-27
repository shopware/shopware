<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Api\ApiException;
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
     */
    public function __construct(
        private array $userRoles,
        private array $integrationRoles,
    ) {
    }

    /**
     * The roles of the given user or integration, or null if it is unknown.
     * An app has no roles of its own: it acts through its integration, so look up the integration instead.
     */
    public function rolesFor(string $id, IdentityType $type): ?AclRoles
    {
        return match ($type) {
            IdentityType::User => $this->userRoles[$id] ?? null,
            IdentityType::Integration, IdentityType::AppIntegration => $this->integrationRoles[$id] ?? null,
            IdentityType::App => throw ApiException::appHasNoRoles(),
        };
    }
}

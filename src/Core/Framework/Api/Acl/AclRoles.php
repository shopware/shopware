<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl;

use Shopware\Core\Framework\Log\Package;

/**
 * The ACL roles of a user or integration. An admin's roles are unrestricted: they allow everything.
 *
 * @internal
 */
#[Package('framework')]
final readonly class AclRoles
{
    /**
     * @param list<string> $roleIds
     */
    public function __construct(
        public bool $unrestricted,
        public array $roleIds,
    ) {
    }

    /**
     * Keeps only the roles that are also in the given list. Unrestricted roles allow everything, so they become
     * exactly the given ones.
     *
     * @param list<string> $roleIds
     */
    public function intersect(array $roleIds): self
    {
        if ($this->unrestricted) {
            return new self(false, $roleIds);
        }

        return new self(false, array_values(array_intersect($this->roleIds, $roleIds)));
    }
}

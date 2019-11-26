<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Context;

use Shopware\Core\Framework\Acl\Permission\AclPermissionCollection;

class AdminApiSource implements ContextSource
{
    /**
     * @var string|null
     */
    private $userId;

    /**
     * @var string|null
     */
    private $integrationId;

    /**
     * @var bool
     */
    private $isAdmin;

    /**
     * @var AclPermissionCollection
     */
    private $permissions;

    public function __construct(?string $userId, ?string $integrationId = null)
    {
        $this->userId = $userId;
        $this->integrationId = $integrationId;
        $this->permissions = new AclPermissionCollection();
    }

    public function getUserId(): ?string
    {
        return $this->userId;
    }

    public function getIntegrationId(): ?string
    {
        return $this->integrationId;
    }

    public function setIsAdmin(bool $isAdmin): void
    {
        $this->isAdmin = $isAdmin;
    }

    public function addPermissions(array $permissions): void
    {
        foreach ($permissions as $permission) {
            $this->permissions->add($permission);
        }
    }

    public function isAllowed(string $resource, string $privilege): bool
    {
        if ($this->isAdmin) {
            return true;
        }

        return $this->permissions->isAllowed($resource, $privilege);
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Context;

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
     * @var array
     */
    private $permissions = [];

    public function __construct(?string $userId, ?string $integrationId = null)
    {
        $this->userId = $userId;
        $this->integrationId = $integrationId;
        $this->isAdmin = false;
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

    public function setPermissions(array $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function isAllowed(string $privilege): bool
    {
        if ($this->isAdmin) {
            return true;
        }

        return \in_array($privilege, $this->permissions, true);
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }
}

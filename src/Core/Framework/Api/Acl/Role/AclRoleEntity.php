<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Role;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Integration\IntegrationCollection;
use Shopware\Core\System\User\UserCollection;

class AclRoleEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var array
     */
    protected $privileges = [];

    /**
     * @var UserCollection|null
     */
    protected $users;

    /**
     * @var AppEntity|null
     */
    protected $app;

    /**
     * @var IntegrationCollection|null
     */
    protected $integrations;

    protected ?\DateTimeInterface $deletedAt;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getUsers(): ?UserCollection
    {
        return $this->users;
    }

    public function setUsers(UserCollection $users): void
    {
        $this->users = $users;
    }

    public function getPrivileges(): array
    {
        return $this->privileges;
    }

    public function setPrivileges(array $privileges): void
    {
        $this->privileges = $privileges;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getApp(): ?AppEntity
    {
        return $this->app;
    }

    public function setApp(?AppEntity $app): void
    {
        $this->app = $app;
    }

    public function getIntegrations(): ?IntegrationCollection
    {
        return $this->integrations;
    }

    public function setIntegrations(IntegrationCollection $integrations): void
    {
        $this->integrations = $integrations;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(\DateTimeInterface $deletedAt): void
    {
        $this->deletedAt = $deletedAt;
    }
}

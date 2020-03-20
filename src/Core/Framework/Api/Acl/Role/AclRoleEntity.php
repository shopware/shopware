<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Role;

use Shopware\Core\Framework\Api\Acl\Resource\AclResourceCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\User\UserCollection;

class AclRoleEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var AclResourceCollection|null
     */
    protected $aclResources;

    /**
     * @var UserCollection|null
     */
    protected $users;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAclResources(): ?AclResourceCollection
    {
        return $this->aclResources;
    }

    public function setAclResources(AclResourceCollection $aclResources): void
    {
        $this->aclResources = $aclResources;
    }

    public function getUsers(): ?UserCollection
    {
        return $this->users;
    }

    public function setUsers(UserCollection $users): void
    {
        $this->users = $users;
    }

    public function getApiAlias(): string
    {
        return 'dal_acl_rote';
    }
}

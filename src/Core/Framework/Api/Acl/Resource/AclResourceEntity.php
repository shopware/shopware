<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Resource;

use Shopware\Core\Framework\Api\Acl\Permission\AclPermission;
use Shopware\Core\Framework\Api\Acl\Role\AclRoleEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;

class AclResourceEntity extends Entity
{
    /**
     * @var string
     */
    protected $resource;

    /**
     * @var string
     */
    protected $privilege;

    /**
     * @var string
     */
    protected $aclRoleId;

    /**
     * @var AclRoleEntity
     */
    protected $aclRole;

    public function getResource(): string
    {
        return $this->resource;
    }

    public function setResource(string $resource): void
    {
        $this->resource = $resource;
    }

    public function getPrivilege(): string
    {
        return $this->privilege;
    }

    public function setPrivilege(string $privilege): void
    {
        $this->privilege = $privilege;
    }

    public function getAclRole(): AclRoleEntity
    {
        return $this->aclRole;
    }

    public function setAclRole(AclRoleEntity $aclRole): void
    {
        $this->aclRole = $aclRole;
    }

    public function getAclRoleId(): string
    {
        return $this->aclRoleId;
    }

    public function setAclRoleId(string $aclRoleId): void
    {
        $this->aclRoleId = $aclRoleId;
    }

    public function intoAclPermission(): AclPermission
    {
        return new AclPermission($this->resource, $this->privilege);
    }

    public function getApiAlias(): string
    {
        return 'dal_acl_resource';
    }
}

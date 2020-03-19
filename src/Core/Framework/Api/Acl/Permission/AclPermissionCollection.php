<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Permission;

use Shopware\Core\Framework\Api\Acl\Resource\AclResourceCollection;
use Shopware\Core\Framework\Struct\Collection;

class AclPermissionCollection extends Collection
{
    public function __construct(?AclResourceCollection $resourceCollection = null)
    {
        parent::__construct([]);
        if (!$resourceCollection) {
            return;
        }

        foreach ($resourceCollection as $resourceEntity) {
            $this->add($resourceEntity->intoAclPermission());
        }
    }

    /**
     * @param AclPermission $permission
     */
    public function add($permission): void
    {
        $this->set($permission->getResource() . $permission->getPrivilege(), $permission);
    }

    public function isAllowed(string $resource, string $privilege): bool
    {
        return $this->has($resource . $privilege);
    }

    public function getApiAlias(): string
    {
        return 'dal_acl_permission_collection';
    }
}

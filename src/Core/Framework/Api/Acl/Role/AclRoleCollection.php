<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Role;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void               add(AclRoleEntity $entity)
 * @method void               set(string $key, AclRoleEntity $entity)
 * @method AclRoleEntity[]    getIterator()
 * @method AclRoleEntity[]    getElements()
 * @method AclRoleEntity|null get(string $key)
 * @method AclRoleEntity|null first()
 * @method AclRoleEntity|null last()
 */
class AclRoleCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_acl_role_collection';
    }

    protected function getExpectedClass(): string
    {
        return AclRoleEntity::class;
    }
}

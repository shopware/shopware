<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Api\Acl\Resource;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(AclResourceEntity $entity)
 * @method void                   set(string $key, AclResourceEntity $entity)
 * @method AclResourceEntity[]    getIterator()
 * @method AclResourceEntity[]    getElements()
 * @method AclResourceEntity|null get(string $key)
 * @method AclResourceEntity|null first()
 * @method AclResourceEntity|null last()
 */
class AclResourceCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'dal_acl_resource_collection';
    }

    protected function getExpectedClass(): string
    {
        return AclResourceEntity::class;
    }
}

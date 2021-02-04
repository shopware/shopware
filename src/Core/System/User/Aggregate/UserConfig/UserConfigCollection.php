<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Aggregate\UserConfig;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                  add(UserConfigEntity $entity)
 * @method UserConfigEntity[]    getIterator()
 * @method UserConfigEntity[]    getElements()
 * @method UserConfigEntity|null get(string $key)
 * @method UserConfigEntity|null first()
 * @method UserConfigEntity|null last()
 */
class UserConfigCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'user_config_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserConfigEntity::class;
    }
}

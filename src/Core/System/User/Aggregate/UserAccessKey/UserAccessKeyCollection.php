<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Aggregate\UserAccessKey;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                     add(UserAccessKeyEntity $entity)
 * @method void                     set(string $key, UserAccessKeyEntity $entity)
 * @method UserAccessKeyEntity[]    getIterator()
 * @method UserAccessKeyEntity[]    getElements()
 * @method UserAccessKeyEntity|null get(string $key)
 * @method UserAccessKeyEntity|null first()
 * @method UserAccessKeyEntity|null last()
 */
class UserAccessKeyCollection extends EntityCollection
{
    public function getUserIds(): array
    {
        return $this->fmap(function (UserAccessKeyEntity $user) {
            return $user->getUserId();
        });
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(function (UserAccessKeyEntity $user) use ($id) {
            return $user->getUserId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'user_access_key_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserAccessKeyEntity::class;
    }
}

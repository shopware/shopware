<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Aggregate\UserAccessKey;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UserAccessKeyEntity>
 *
 * @package system-settings
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

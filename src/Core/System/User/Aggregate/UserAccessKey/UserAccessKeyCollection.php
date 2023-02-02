<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Aggregate\UserAccessKey;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<UserAccessKeyEntity>
 */
#[Package('system-settings')]
class UserAccessKeyCollection extends EntityCollection
{
    public function getUserIds(): array
    {
        return $this->fmap(fn (UserAccessKeyEntity $user) => $user->getUserId());
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(fn (UserAccessKeyEntity $user) => $user->getUserId() === $id);
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

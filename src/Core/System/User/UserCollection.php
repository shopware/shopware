<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @extends EntityCollection<UserEntity>
 */
class UserCollection extends EntityCollection
{
    public function getLocaleIds(): array
    {
        return $this->fmap(function (UserEntity $user) {
            return $user->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(function (UserEntity $user) use ($id) {
            return $user->getLocaleId() === $id;
        });
    }

    public function getApiAlias(): string
    {
        return 'user_collection';
    }

    protected function getExpectedClass(): string
    {
        return UserEntity::class;
    }
}

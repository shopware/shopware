<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Aggregate\UserAccessKey;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

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

    protected function getExpectedClass(): string
    {
        return UserAccessKeyEntity::class;
    }
}

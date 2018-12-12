<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Aggregate\UserAccessKey;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class UserAccessKeyCollection extends EntityCollection
{
    /**
     * @var UserAccessKeyEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? UserAccessKeyEntity
    {
        return parent::get($id);
    }

    public function current(): UserAccessKeyEntity
    {
        return parent::current();
    }

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

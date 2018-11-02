<?php declare(strict_types=1);

namespace Shopware\Core\System\User\Aggregate\UserAccessKey;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class UserAccessKeyCollection extends EntityCollection
{
    /**
     * @var UserAccessKeyStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? UserAccessKeyStruct
    {
        return parent::get($id);
    }

    public function current(): UserAccessKeyStruct
    {
        return parent::current();
    }

    public function getUserIds(): array
    {
        return $this->fmap(function (UserAccessKeyStruct $user) {
            return $user->getUserId();
        });
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(function (UserAccessKeyStruct $user) use ($id) {
            return $user->getUserId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return UserAccessKeyStruct::class;
    }
}

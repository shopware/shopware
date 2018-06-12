<?php declare(strict_types=1);

namespace Shopware\Core\System\User;

use Shopware\Core\Framework\ORM\EntityCollection;

class UserCollection extends EntityCollection
{
    /**
     * @var UserStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? UserStruct
    {
        return parent::get($id);
    }

    public function current(): UserStruct
    {
        return parent::current();
    }

    public function getLocaleIds(): array
    {
        return $this->fmap(function (UserStruct $user) {
            return $user->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(function (UserStruct $user) use ($id) {
            return $user->getLocaleId() === $id;
        });
    }

    public function getSessionIds(): array
    {
        return $this->fmap(function (UserStruct $user) {
            return $user->getSessionId();
        });
    }

    public function filterBySessionId(string $id): self
    {
        return $this->filter(function (UserStruct $user) use ($id) {
            return $user->getSessionId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return UserStruct::class;
    }
}

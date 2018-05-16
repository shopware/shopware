<?php declare(strict_types=1);

namespace Shopware\System\User\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\System\User\Struct\UserBasicStruct;

class UserBasicCollection extends EntityCollection
{
    /**
     * @var UserBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? UserBasicStruct
    {
        return parent::get($id);
    }

    public function current(): UserBasicStruct
    {
        return parent::current();
    }

    public function getLocaleIds(): array
    {
        return $this->fmap(function (UserBasicStruct $user) {
            return $user->getLocaleId();
        });
    }

    public function filterByLocaleId(string $id): self
    {
        return $this->filter(function (UserBasicStruct $user) use ($id) {
            return $user->getLocaleId() === $id;
        });
    }

    public function getSessionIds(): array
    {
        return $this->fmap(function (UserBasicStruct $user) {
            return $user->getSessionId();
        });
    }

    public function filterBySessionId(string $id): self
    {
        return $this->filter(function (UserBasicStruct $user) use ($id) {
            return $user->getSessionId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return UserBasicStruct::class;
    }
}

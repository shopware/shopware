<?php declare(strict_types=1);

namespace Shopware\Storefront\Account\Page;

use Shopware\Core\Framework\Struct\Struct;

class LoginRequest extends Struct
{
    /** @var string|null */
    protected $username;

    /** @var string|null */
    protected $password;

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): void
    {
        $this->username = $username;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use JsonSerializable;
use Shopware\Core\Framework\Struct\Struct;

class LoginRequest extends Struct implements JsonSerializable
{
    /** @var string|null */
    protected $email;

    /** @var string|null */
    protected $password;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
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

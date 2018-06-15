<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use JsonSerializable;
use Shopware\Core\Framework\Struct\Struct;

class PasswordSaveRequest extends Struct implements JsonSerializable
{
    /** @var string|null */
    protected $password;

    /** @var string|null */
    protected $passwordConfirmation;

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): void
    {
        $this->password = $password;
    }

    public function getPasswordConfirmation(): ?string
    {
        return $this->passwordConfirmation;
    }

    public function setPasswordConfirmation(?string $passwordConfirmation): void
    {
        $this->passwordConfirmation = $passwordConfirmation;
    }
}

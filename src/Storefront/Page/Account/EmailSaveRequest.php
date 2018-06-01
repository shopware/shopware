<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use JsonSerializable;
use Shopware\Framework\Struct\Struct;

class EmailSaveRequest extends Struct implements JsonSerializable
{
    /** @var string|null */
    protected $email;

    /** @var string|null */
    protected $emailConfirmation;

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): void
    {
        $this->email = $email;
    }

    public function getEmailConfirmation(): ?string
    {
        return $this->emailConfirmation;
    }

    public function setEmailConfirmation(?string $emailConfirmation): void
    {
        $this->emailConfirmation = $emailConfirmation;
    }
}

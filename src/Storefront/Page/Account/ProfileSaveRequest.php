<?php declare(strict_types=1);

namespace Shopware\Storefront\Page\Account;

use JsonSerializable;
use Shopware\Framework\Struct\Struct;

class ProfileSaveRequest extends Struct implements JsonSerializable
{
    /** @var string|null */
    protected $firstName;

    /** @var string|null */
    protected $lastName;

    /** @var string|null */
    protected $title;

    /** @var string|null */
    protected $salutation;

    /** @var int|null */
    protected $birthdayDay;

    /** @var int|null */
    protected $birthdayMonth;

    /** @var int|null */
    protected $birthdayYear;

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getSalutation(): ?string
    {
        return $this->salutation;
    }

    public function setSalutation(?string $salutation): void
    {
        $this->salutation = $salutation;
    }

    public function getBirthdayDay(): ?int
    {
        return $this->birthdayDay;
    }

    public function setBirthdayDay(?int $birthdayDay): void
    {
        $this->birthdayDay = $birthdayDay;
    }

    public function getBirthdayMonth(): ?int
    {
        return $this->birthdayMonth;
    }

    public function setBirthdayMonth(?int $birthdayMonth): void
    {
        $this->birthdayMonth = $birthdayMonth;
    }

    public function getBirthdayYear(): ?int
    {
        return $this->birthdayYear;
    }

    public function setBirthdayYear(?int $birthdayYear): void
    {
        $this->birthdayYear = $birthdayYear;
    }

    public function getBirthday()
    {
        if (!$this->birthdayDay || !$this->birthdayMonth || !$this->birthdayYear) {
            return null;
        }

        return new \DateTime(sprintf(
            '%s-%s-%s',
            (int) $this->birthdayYear,
            (int) $this->birthdayMonth,
            (int) $this->birthdayDay
        ));
    }
}

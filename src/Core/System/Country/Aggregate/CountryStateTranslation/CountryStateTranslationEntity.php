<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryStateTranslation;

use Shopware\Core\Framework\DataAbstractionLayer\TranslationEntity;
use Shopware\Core\System\Country\Aggregate\CountryState\CountryStateEntity;

class CountryStateTranslationEntity extends TranslationEntity
{
    /**
     * @var string
     */
    protected $countryStateId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var CountryStateEntity|null
     */
    protected $countryState;

    /**
     * @var array|null
     */
    protected $customFields;

    public function getCountryStateId(): string
    {
        return $this->countryStateId;
    }

    public function setCountryStateId(string $countryStateId): void
    {
        $this->countryStateId = $countryStateId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getCountryState(): ?CountryStateEntity
    {
        return $this->countryState;
    }

    public function setCountryState(CountryStateEntity $countryState): void
    {
        $this->countryState = $countryState;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }
}

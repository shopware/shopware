<?php declare(strict_types=1);

namespace Shopware\AreaCountry\Struct;

use Shopware\Framework\Struct\Struct;

class AreaCountryBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string|null
     */
    protected $iso;

    /**
     * @var string
     */
    protected $areaUuid;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var bool
     */
    protected $shippingFree;

    /**
     * @var bool
     */
    protected $taxFree;

    /**
     * @var bool
     */
    protected $taxfreeForVatId;

    /**
     * @var bool
     */
    protected $taxfreeVatidChecked;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string|null
     */
    protected $iso3;

    /**
     * @var bool
     */
    protected $displayStateInRegistration;

    /**
     * @var bool
     */
    protected $forceStateInRegistration;

    /**
     * @var string
     */
    protected $name;

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function setUuid(string $uuid): void
    {
        $this->uuid = $uuid;
    }

    public function getIso(): ?string
    {
        return $this->iso;
    }

    public function setIso(?string $iso): void
    {
        $this->iso = $iso;
    }

    public function getAreaUuid(): string
    {
        return $this->areaUuid;
    }

    public function setAreaUuid(string $areaUuid): void
    {
        $this->areaUuid = $areaUuid;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getShippingFree(): bool
    {
        return $this->shippingFree;
    }

    public function setShippingFree(bool $shippingFree): void
    {
        $this->shippingFree = $shippingFree;
    }

    public function getTaxFree(): bool
    {
        return $this->taxFree;
    }

    public function setTaxFree(bool $taxFree): void
    {
        $this->taxFree = $taxFree;
    }

    public function getTaxfreeForVatId(): bool
    {
        return $this->taxfreeForVatId;
    }

    public function setTaxfreeForVatId(bool $taxfreeForVatId): void
    {
        $this->taxfreeForVatId = $taxfreeForVatId;
    }

    public function getTaxfreeVatidChecked(): bool
    {
        return $this->taxfreeVatidChecked;
    }

    public function setTaxfreeVatidChecked(bool $taxfreeVatidChecked): void
    {
        $this->taxfreeVatidChecked = $taxfreeVatidChecked;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getIso3(): ?string
    {
        return $this->iso3;
    }

    public function setIso3(?string $iso3): void
    {
        $this->iso3 = $iso3;
    }

    public function getDisplayStateInRegistration(): bool
    {
        return $this->displayStateInRegistration;
    }

    public function setDisplayStateInRegistration(bool $displayStateInRegistration): void
    {
        $this->displayStateInRegistration = $displayStateInRegistration;
    }

    public function getForceStateInRegistration(): bool
    {
        return $this->forceStateInRegistration;
    }

    public function setForceStateInRegistration(bool $forceStateInRegistration): void
    {
        $this->forceStateInRegistration = $forceStateInRegistration;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

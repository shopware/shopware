<?php

namespace Shopware\Country\Struct;

use Shopware\Framework\Struct\Struct;

class CountryIdentity extends Struct
{
    /**
     * @var int
     */
    protected $id;

    /**
     * @var string
     */
    protected $countryName;

    /**
     * @var string
     */
    protected $countryIso;

    /**
     * @var int
     */
    protected $areaId;

    /**
     * @var string
     */
    protected $countryEn;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var string|null
     */
    protected $notice;

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
    protected $taxFreeUstId;

    /**
     * @var bool
     */
    protected $taxFreeUstidChecked;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var string
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

    public function getId(): int
    {
        return $this->id;
    }

    public function setId(int $id): void
    {
        $this->id = $id;
    }

    public function getCountryName(): string
    {
        return $this->countryName;
    }

    public function setCountryName(string $countryName): void
    {
        $this->countryName = $countryName;
    }

    public function getCountryIso(): string
    {
        return $this->countryIso;
    }

    public function setCountryIso(string $countryIso): void
    {
        $this->countryIso = $countryIso;
    }

    public function getAreaId(): int
    {
        return $this->areaId;
    }

    public function setAreaId(int $areaId): void
    {
        $this->areaId = $areaId;
    }

    public function getCountryEn(): string
    {
        return $this->countryEn;
    }

    public function setCountryEn(string $countryEn): void
    {
        $this->countryEn = $countryEn;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getNotice(): ?string
    {
        return $this->notice;
    }

    public function setNotice(?string $notice): void
    {
        $this->notice = $notice;
    }

    public function isShippingFree(): bool
    {
        return $this->shippingFree;
    }

    public function setShippingFree(bool $shippingFree): void
    {
        $this->shippingFree = $shippingFree;
    }

    public function isTaxFree(): bool
    {
        return $this->taxFree;
    }

    public function setTaxFree(bool $taxFree): void
    {
        $this->taxFree = $taxFree;
    }

    public function isTaxFreeUstId(): bool
    {
        return $this->taxFreeUstId;
    }

    public function setTaxFreeUstId(bool $taxFreeUstId): void
    {
        $this->taxFreeUstId = $taxFreeUstId;
    }

    public function isTaxFreeUstidChecked(): bool
    {
        return $this->taxFreeUstidChecked;
    }

    public function setTaxFreeUstidChecked(bool $taxFreeUstidChecked): void
    {
        $this->taxFreeUstidChecked = $taxFreeUstidChecked;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getIso3(): string
    {
        return $this->iso3;
    }

    public function setIso3(string $iso3): void
    {
        $this->iso3 = $iso3;
    }

    public function isDisplayStateInRegistration(): bool
    {
        return $this->displayStateInRegistration;
    }

    public function setDisplayStateInRegistration(bool $displayStateInRegistration): void
    {
        $this->displayStateInRegistration = $displayStateInRegistration;
    }

    public function isForceStateInRegistration(): bool
    {
        return $this->forceStateInRegistration;
    }

    public function setForceStateInRegistration(bool $forceStateInRegistration): void
    {
        $this->forceStateInRegistration = $forceStateInRegistration;
    }
}
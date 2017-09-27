<?php declare(strict_types=1);

namespace Shopware\TaxAreaRule\Struct;

use Shopware\Framework\Struct\Struct;

class TaxAreaRuleBasicStruct extends Struct
{
    /**
     * @var string
     */
    protected $uuid;

    /**
     * @var string|null
     */
    protected $areaUuid;

    /**
     * @var string|null
     */
    protected $areaCountryUuid;

    /**
     * @var string|null
     */
    protected $areaCountryStateUuid;

    /**
     * @var string
     */
    protected $taxUuid;

    /**
     * @var string
     */
    protected $customerGroupUuid;

    /**
     * @var float
     */
    protected $taxRate;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

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

    public function getAreaUuid(): ?string
    {
        return $this->areaUuid;
    }

    public function setAreaUuid(?string $areaUuid): void
    {
        $this->areaUuid = $areaUuid;
    }

    public function getAreaCountryUuid(): ?string
    {
        return $this->areaCountryUuid;
    }

    public function setAreaCountryUuid(?string $areaCountryUuid): void
    {
        $this->areaCountryUuid = $areaCountryUuid;
    }

    public function getAreaCountryStateUuid(): ?string
    {
        return $this->areaCountryStateUuid;
    }

    public function setAreaCountryStateUuid(?string $areaCountryStateUuid): void
    {
        $this->areaCountryStateUuid = $areaCountryStateUuid;
    }

    public function getTaxUuid(): string
    {
        return $this->taxUuid;
    }

    public function setTaxUuid(string $taxUuid): void
    {
        $this->taxUuid = $taxUuid;
    }

    public function getCustomerGroupUuid(): string
    {
        return $this->customerGroupUuid;
    }

    public function setCustomerGroupUuid(string $customerGroupUuid): void
    {
        $this->customerGroupUuid = $customerGroupUuid;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function setTaxRate(float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    public function getActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
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

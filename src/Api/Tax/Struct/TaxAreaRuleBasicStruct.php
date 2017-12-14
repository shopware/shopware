<?php declare(strict_types=1);

namespace Shopware\Api\Tax\Struct;

use Shopware\Api\Entity\Entity;

class TaxAreaRuleBasicStruct extends Entity
{
    /**
     * @var string|null
     */
    protected $countryAreaUuid;

    /**
     * @var string|null
     */
    protected $countryUuid;

    /**
     * @var string|null
     */
    protected $countryStateUuid;

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
     * @var string
     */
    protected $name;

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

    public function getCountryAreaUuid(): ?string
    {
        return $this->countryAreaUuid;
    }

    public function setCountryAreaUuid(?string $countryAreaUuid): void
    {
        $this->countryAreaUuid = $countryAreaUuid;
    }

    public function getCountryUuid(): ?string
    {
        return $this->countryUuid;
    }

    public function setCountryUuid(?string $countryUuid): void
    {
        $this->countryUuid = $countryUuid;
    }

    public function getCountryStateUuid(): ?string
    {
        return $this->countryStateUuid;
    }

    public function setCountryStateUuid(?string $countryStateUuid): void
    {
        $this->countryStateUuid = $countryStateUuid;
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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
}

<?php declare(strict_types=1);

namespace Shopware\Api\Customer\Struct;

use Shopware\Api\Entity\Entity;

class CustomerGroupBasicStruct extends Entity
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var bool
     */
    protected $displayGross;

    /**
     * @var bool
     */
    protected $inputGross;

    /**
     * @var bool
     */
    protected $hasGlobalDiscount;

    /**
     * @var float|null
     */
    protected $percentageGlobalDiscount;

    /**
     * @var float|null
     */
    protected $minimumOrderAmount;

    /**
     * @var float|null
     */
    protected $minimumOrderAmountSurcharge;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDisplayGross(): bool
    {
        return $this->displayGross;
    }

    public function setDisplayGross(bool $displayGross): void
    {
        $this->displayGross = $displayGross;
    }

    public function getInputGross(): bool
    {
        return $this->inputGross;
    }

    public function setInputGross(bool $inputGross): void
    {
        $this->inputGross = $inputGross;
    }

    public function getHasGlobalDiscount(): bool
    {
        return $this->hasGlobalDiscount;
    }

    public function setHasGlobalDiscount(bool $hasGlobalDiscount): void
    {
        $this->hasGlobalDiscount = $hasGlobalDiscount;
    }

    public function getPercentageGlobalDiscount(): ?float
    {
        return $this->percentageGlobalDiscount;
    }

    public function setPercentageGlobalDiscount(?float $percentageGlobalDiscount): void
    {
        $this->percentageGlobalDiscount = $percentageGlobalDiscount;
    }

    public function getMinimumOrderAmount(): ?float
    {
        return $this->minimumOrderAmount;
    }

    public function setMinimumOrderAmount(?float $minimumOrderAmount): void
    {
        $this->minimumOrderAmount = $minimumOrderAmount;
    }

    public function getMinimumOrderAmountSurcharge(): ?float
    {
        return $this->minimumOrderAmountSurcharge;
    }

    public function setMinimumOrderAmountSurcharge(?float $minimumOrderAmountSurcharge): void
    {
        $this->minimumOrderAmountSurcharge = $minimumOrderAmountSurcharge;
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

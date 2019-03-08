<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount\CustomerGroupDiscountCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class CustomerGroupEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var string|null
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
     * @var \DateTimeInterface|null
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    /**
     * @var CustomerGroupDiscountCollection|null
     */
    protected $discounts;

    /**
     * @var CustomerGroupTranslationCollection|null
     */
    protected $translations;

    /**
     * @var CustomerCollection|null
     */
    protected $customers;

    /**
     * @var array|null
     */
    protected $attributes;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getDiscounts(): ?CustomerGroupDiscountCollection
    {
        return $this->discounts;
    }

    public function setDiscounts(CustomerGroupDiscountCollection $discounts): void
    {
        $this->discounts = $discounts;
    }

    public function getTranslations(): ?CustomerGroupTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(CustomerGroupTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCustomers(): ?CustomerCollection
    {
        return $this->customers;
    }

    public function setCustomers(CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }
}

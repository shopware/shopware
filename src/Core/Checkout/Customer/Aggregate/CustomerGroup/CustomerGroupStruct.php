<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount\CustomerGroupDiscountCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationCollection;
use Shopware\Core\Checkout\Customer\CustomerCollection;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleCollection;

class CustomerGroupStruct extends Entity
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

    /**
     * @var CustomerGroupDiscountCollection|null
     */
    protected $discounts;

    /**
     * @var CustomerGroupTranslationCollection|null
     */
    protected $translations;

    /**
     * @var TaxAreaRuleCollection|null
     */
    protected $taxAreaRules;

    /**
     * @var CustomerCollection|null
     */
    protected $customers;

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

    public function getTaxAreaRules(): ?TaxAreaRuleCollection
    {
        return $this->taxAreaRules;
    }

    public function setTaxAreaRules(?TaxAreaRuleCollection $taxAreaRules): void
    {
        $this->taxAreaRules = $taxAreaRules;
    }

    public function getCustomers(): ?CustomerCollection
    {
        return $this->customers;
    }

    public function setCustomers(?CustomerCollection $customers): void
    {
        $this->customers = $customers;
    }
}

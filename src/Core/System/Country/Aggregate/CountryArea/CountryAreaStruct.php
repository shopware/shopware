<?php declare(strict_types=1);

namespace Shopware\Core\System\Country\Aggregate\CountryArea;

use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Country\CountryCollection;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleCollection;

class CountryAreaStruct extends Entity
{
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

    /**
     * @var CountryCollection|null
     */
    protected $countries;

    /**
     * @var CountryAreaCollection|null
     */
    protected $translations;

    /**
     * @var TaxAreaRuleCollection|null
     */
    protected $taxAreaRules;

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

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getCountries(): ?CountryCollection
    {
        return $this->countries;
    }

    public function setCountries(CountryCollection $countries): void
    {
        $this->countries = $countries;
    }

    public function getTranslations(): ?CountryAreaCollection
    {
        return $this->translations;
    }

    public function setTranslations(CountryAreaCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getTaxAreaRules(): ?TaxAreaRuleCollection
    {
        return $this->taxAreaRules;
    }

    public function setTaxAreaRules(TaxAreaRuleCollection $taxAreaRules): void
    {
        $this->taxAreaRules = $taxAreaRules;
    }
}

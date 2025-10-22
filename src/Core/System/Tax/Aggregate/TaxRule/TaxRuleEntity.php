<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxRule;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Tax\Aggregate\TaxRuleType\TaxRuleTypeEntity;
use Shopware\Core\System\Tax\TaxEntity;

/**
 * @phpstan-type TaxRuleData array{states?: list<string>, zipCode?: string, fromZipCode?: string, toZipCode?: string}
 */
#[Package('checkout')]
class TaxRuleEntity extends Entity
{
    use EntityIdTrait;

    protected string $taxId;

    protected ?TaxEntity $tax = null;

    protected string $countryId;

    protected ?CountryEntity $country = null;

    protected string $taxRuleTypeId;

    protected ?TaxRuleTypeEntity $type = null;

    protected float $taxRate;

    /**
     * @var TaxRuleData|null
     */
    protected ?array $data = null;

    protected ?\DateTimeInterface $activeFrom = null;

    public function getTaxId(): string
    {
        return $this->taxId;
    }

    public function setTaxId(string $taxId): void
    {
        $this->taxId = $taxId;
    }

    public function getTax(): ?TaxEntity
    {
        return $this->tax;
    }

    public function setTax(?TaxEntity $tax): void
    {
        $this->tax = $tax;
    }

    public function getCountryId(): string
    {
        return $this->countryId;
    }

    public function setCountryId(string $countryId): void
    {
        $this->countryId = $countryId;
    }

    public function getCountry(): ?CountryEntity
    {
        return $this->country;
    }

    public function setCountry(?CountryEntity $country): void
    {
        $this->country = $country;
    }

    public function getTaxRuleTypeId(): string
    {
        return $this->taxRuleTypeId;
    }

    public function setTaxRuleTypeId(string $taxRuleTypeId): void
    {
        $this->taxRuleTypeId = $taxRuleTypeId;
    }

    /**
     * @deprecated tag:v6.8.0 - reason:return-type-change - return type will be nullable and condition will be removed
     */
    public function getType(): TaxRuleTypeEntity
    {
        if ($this->type === null) {
            return new TaxRuleTypeEntity();
        }

        return $this->type;
    }

    public function setType(TaxRuleTypeEntity $type): void
    {
        $this->type = $type;
    }

    /**
     * @codeCoverageIgnore
     */
    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function setTaxRate(float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    /**
     * @return TaxRuleData|null
     */
    public function getData(): ?array
    {
        return $this->data;
    }

    /**
     * @param TaxRuleData|null $data
     */
    public function setData(?array $data): void
    {
        $this->data = $data;
    }

    public function getActiveFrom(): ?\DateTimeInterface
    {
        return $this->activeFrom;
    }

    public function setActiveFrom(?\DateTimeInterface $activeFrom): void
    {
        $this->activeFrom = $activeFrom;
    }
}

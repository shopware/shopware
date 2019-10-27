<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax\Aggregate\TaxAreaRule;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Country\CountryEntity;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleType\TaxAreaRuleTypeEntity;
use Shopware\Core\System\Tax\TaxEntity;

class TaxAreaRuleEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $taxId;

    /**
     * @var TaxEntity|null
     */
    protected $tax;

    /**
     * @var string
     */
    protected $countryId;

    /**
     * @var CountryEntity|null
     */
    protected $country;

    /**
     * @var string
     */
    protected $taxAreaRuleTypeId;

    /**
     * @var TaxAreaRuleTypeEntity
     */
    protected $taxAreaRuleType;

    /**
     * @var float
     */
    protected $taxRate;

    /**
     * @var array|null
     */
    protected $data;

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

    public function getTaxAreaRuleTypeId(): string
    {
        return $this->taxAreaRuleTypeId;
    }

    public function setTaxAreaRuleTypeId(string $taxAreaRuleTypeId): void
    {
        $this->taxAreaRuleTypeId = $taxAreaRuleTypeId;
    }

    public function getTaxAreaRuleType(): TaxAreaRuleTypeEntity
    {
        return $this->taxAreaRuleType;
    }

    public function setTaxAreaRuleType(TaxAreaRuleTypeEntity $taxAreaRuleType): void
    {
        $this->taxAreaRuleType = $taxAreaRuleType;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function setTaxRate(float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): void
    {
        $this->data = $data;
    }
}

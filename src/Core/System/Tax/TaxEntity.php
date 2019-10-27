<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleCollection;

class TaxEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var float
     */
    protected $taxRate;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var ProductCollection|null
     */
    protected $products;

    /**
     * @var array|null
     */
    protected $customFields;

    /**
     * @var TaxAreaRuleCollection|null
     */
    protected $taxAreaRules;

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

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getCustomFields(): ?array
    {
        return $this->customFields;
    }

    public function setCustomFields(?array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getTaxAreaRules(): ?TaxAreaRuleCollection
    {
        return $this->taxAreaRules;
    }

    public function setTaxAreaRules(?TaxAreaRuleCollection $taxAreaRules): void
    {
        $this->taxAreaRules = $taxAreaRules;
    }
}

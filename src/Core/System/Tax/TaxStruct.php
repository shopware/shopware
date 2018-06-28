<?php declare(strict_types=1);

namespace Shopware\Core\System\Tax;

use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\ORM\Entity;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleCollection;

class TaxStruct extends Entity
{
    /**
     * @var float
     */
    protected $rate;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var \DateTime|null
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var TaxAreaRuleCollection|null
     */
    protected $areaRules;

    /**
     * @var ProductCollection|null
     */
    protected $products;

    /**
     * @var ProductServiceCollection|null
     */
    protected $productServices;

    public function getRate(): float
    {
        return $this->rate;
    }

    public function setRate(float $rate): void
    {
        $this->rate = $rate;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
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

    public function getAreaRules(): ?TaxAreaRuleCollection
    {
        return $this->areaRules;
    }

    public function setAreaRules(TaxAreaRuleCollection $areaRules): void
    {
        $this->areaRules = $areaRules;
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getProductServices(): ?ProductServiceCollection
    {
        return $this->productServices;
    }

    public function setProductServices(ProductServiceCollection $productServices): void
    {
        $this->productServices = $productServices;
    }
}

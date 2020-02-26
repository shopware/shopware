<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Api\Converter\fixtures;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\Tax\TaxEntity;

class DeprecatedEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var int
     */
    protected $price;

    /**
     * @var int[]
     */
    protected $prices;

    /**
     * @var string
     */
    protected $taxId;

    /**
     * @var TaxEntity
     */
    protected $tax;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var ProductEntity
     */
    protected $product;

    protected $_entityName = 'deprecated-entity';

    /**
     * @deprecated in 6.1.0 will be removed in 6.2.0 use `getPrices()` instead
     */
    public function getPrice(): int
    {
        return $this->price;
    }

    /**
     * @deprecated in 6.1.0 will be removed in 6.2.0 use `setPrices()` instead
     */
    public function setPrice(int $price): void
    {
        $this->price = $price;
    }

    public function getPrices(): array
    {
        return $this->prices;
    }

    public function setPrices(array $prices): void
    {
        $this->prices = $prices;
    }

    /**
     * @deprecated in 6.1.0 will be removed in 6.2.0
     */
    public function getTaxId(): string
    {
        return $this->taxId;
    }

    /**
     * @deprecated in 6.1.0 will be removed in 6.2.0
     */
    public function setTaxId(string $taxId): void
    {
        $this->taxId = $taxId;
    }

    /**
     * @deprecated in 6.1.0 will be removed in 6.2.0
     */
    public function getTax(): TaxEntity
    {
        return $this->tax;
    }

    /**
     * @deprecated in 6.1.0 will be removed in 6.2.0
     */
    public function setTax(TaxEntity $tax): void
    {
        $this->tax = $tax;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProduct(): ProductEntity
    {
        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductCrossSellingAssignedProducts;

use Shopware\Core\Content\Product\Aggregate\ProductCrossSelling\ProductCrossSellingEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductCrossSellingAssignedProductsEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $crossSellingId;

    /**
     * @var string
     */
    protected $productId;

    /**
     * @var ProductEntity|null
     */
    protected $product;

    /**
     * @var ProductCrossSellingEntity|null
     */
    protected $crossSelling;

    /**
     * @var int
     */
    protected $position;

    public function getCrossSellingId(): string
    {
        return $this->crossSellingId;
    }

    public function setCrossSellingId(string $crossSellingId): void
    {
        $this->crossSellingId = $crossSellingId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getCrossSelling(): ?ProductCrossSellingEntity
    {
        return $this->crossSelling;
    }

    public function setCrossSelling(?ProductCrossSellingEntity $crossSelling): void
    {
        $this->crossSelling = $crossSelling;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductVisibility;

use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('inventory')]
class ProductVisibilityEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var int
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $visibility;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $productId;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannelId;

    /**
     * @var ProductEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $product;

    /**
     * @var SalesChannelEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $salesChannel;

    public function getVisibility(): int
    {
        return $this->visibility;
    }

    public function setVisibility(int $visibility): void
    {
        $this->visibility = $visibility;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getSalesChannelId(): string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }
}

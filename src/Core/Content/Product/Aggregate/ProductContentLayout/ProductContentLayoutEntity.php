<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductContentLayout;

use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('inventory')]
class ProductContentLayoutEntity extends Entity
{
    use EntityIdTrait;

    protected string $productId;

    protected string $productVersionId;

    protected ?string $salesChannelId = null;

    protected string $contentLayoutId;

    protected ?ProductEntity $product = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?ContentLayoutEntity $contentLayout = null;

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getProductVersionId(): string
    {
        return $this->productVersionId;
    }

    public function setProductVersionId(string $productVersionId): void
    {
        $this->productVersionId = $productVersionId;
    }

    public function getSalesChannelId(): ?string
    {
        return $this->salesChannelId;
    }

    public function setSalesChannelId(?string $salesChannelId): void
    {
        $this->salesChannelId = $salesChannelId;
    }

    public function getContentLayoutId(): string
    {
        return $this->contentLayoutId;
    }

    public function setContentLayoutId(string $contentLayoutId): void
    {
        $this->contentLayoutId = $contentLayoutId;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getSalesChannel(): ?SalesChannelEntity
    {
        return $this->salesChannel;
    }

    public function setSalesChannel(?SalesChannelEntity $salesChannel): void
    {
        $this->salesChannel = $salesChannel;
    }

    public function getContentLayout(): ?ContentLayoutEntity
    {
        return $this->contentLayout;
    }

    public function setContentLayout(?ContentLayoutEntity $contentLayout): void
    {
        $this->contentLayout = $contentLayout;
    }
}

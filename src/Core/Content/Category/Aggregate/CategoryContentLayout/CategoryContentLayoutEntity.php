<?php declare(strict_types=1);

namespace Shopware\Core\Content\Category\Aggregate\CategoryContentLayout;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\ContentSystem\Layout\Entity\ContentLayoutEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;

#[Package('discovery')]
class CategoryContentLayoutEntity extends Entity
{
    use EntityIdTrait;

    protected string $categoryId;

    protected string $categoryVersionId;

    protected ?string $salesChannelId = null;

    protected string $contentLayoutId;

    protected ?CategoryEntity $category = null;

    protected ?SalesChannelEntity $salesChannel = null;

    protected ?ContentLayoutEntity $contentLayout = null;

    public function getCategoryId(): string
    {
        return $this->categoryId;
    }

    public function setCategoryId(string $categoryId): void
    {
        $this->categoryId = $categoryId;
    }

    public function getCategoryVersionId(): string
    {
        return $this->categoryVersionId;
    }

    public function setCategoryVersionId(string $categoryVersionId): void
    {
        $this->categoryVersionId = $categoryVersionId;
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

    public function getCategory(): ?CategoryEntity
    {
        return $this->category;
    }

    public function setCategory(?CategoryEntity $category): void
    {
        $this->category = $category;
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

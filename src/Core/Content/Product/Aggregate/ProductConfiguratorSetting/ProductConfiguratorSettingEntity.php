<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting;

use Shopware\Core\Content\Media\MediaDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ProductConfiguratorSettingEntity extends Entity
{
    use EntityIdTrait;
    /**
     * @var string
     */
    protected $productId;

    /**
     * @var string
     */
    protected $optionId;

    /**
     * @var string
     */
    protected $mediaId;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var array|null
     */
    protected $price;

    /**
     * @var PropertyGroupOptionEntity
     */
    protected $option;

    /**
     * @var MediaDefinition|null
     */
    protected $media;

    /**
     * @var bool
     */
    protected $selected = false;

    /**
     * @var ProductEntity|null
     */
    protected $product;

    /**
     * @var array|null
     */
    protected $attributes;

    /**
     * @var \DateTimeInterface
     */
    protected $createdAt;

    /**
     * @var \DateTimeInterface|null
     */
    protected $updatedAt;

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getOptionId(): string
    {
        return $this->optionId;
    }

    public function setOptionId(string $optionId): void
    {
        $this->optionId = $optionId;
    }

    public function getPrice(): ?array
    {
        return $this->price;
    }

    public function setPrice(array $price): void
    {
        $this->price = $price;
    }

    public function getOption(): PropertyGroupOptionEntity
    {
        return $this->option;
    }

    public function setOption(PropertyGroupOptionEntity $option): void
    {
        $this->option = $option;
    }

    public function getMediaId(): string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
    }

    public function getMedia(): ?MediaDefinition
    {
        return $this->media;
    }

    public function setMedia(?MediaDefinition $media): void
    {
        $this->media = $media;
    }

    public function isSelected(): bool
    {
        return $this->selected;
    }

    public function setSelected(bool $selected): void
    {
        $this->selected = $selected;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(ProductEntity $product): void
    {
        $this->product = $product;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): void
    {
        $this->attributes = $attributes;
    }
}

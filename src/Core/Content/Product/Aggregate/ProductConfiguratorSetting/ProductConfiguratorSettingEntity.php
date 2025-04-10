<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class ProductConfiguratorSettingEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected string $productId;

    protected string $optionId;

    protected ?string $mediaId = null;

    protected int $position;

    /**
     * @var array<Price>|null
     */
    protected ?array $price = null;

    protected ?PropertyGroupOptionEntity $option = null;

    protected ?MediaEntity $media = null;

    protected bool $selected = false;

    protected ?ProductEntity $product = null;

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

    /**
     * @return array<Price>|null
     */
    public function getPrice(): ?array
    {
        return $this->price;
    }

    /**
     * @param array<Price> $price
     */
    public function setPrice(array $price): void
    {
        $this->price = $price;
    }

    public function getOption(): ?PropertyGroupOptionEntity
    {
        return $this->option;
    }

    public function setOption(PropertyGroupOptionEntity $option): void
    {
        $this->option = $option;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
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

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
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
}

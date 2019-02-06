<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationCollection;
use Shopware\Core\Content\Configuration\ConfigurationGroupEntity;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorCollection;
use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class ConfigurationGroupOptionEntity extends Entity
{
    use EntityIdTrait;

    /**
     * @var string
     */
    protected $groupId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var int
     */
    protected $position;

    /**
     * @var string|null
     */
    protected $colorHexCode;

    /**
     * @var string|null
     */
    protected $mediaId;

    /**
     * @var ConfigurationGroupEntity|null
     */
    protected $group;

    /**
     * @var ConfigurationGroupOptionTranslationCollection|null
     */
    protected $translations;

    /**
     * @var ProductConfiguratorCollection|null
     */
    protected $productConfigurators;

    /**
     * @var ProductServiceCollection|null
     */
    protected $productServices;

    /**
     * @var ProductCollection|null
     */
    protected $productDatasheets;

    /**
     * @var ProductCollection|null
     */
    protected $productVariations;

    /**
     * @var \DateTime
     */
    protected $createdAt;

    /**
     * @var \DateTime|null
     */
    protected $updatedAt;

    /**
     * @var MediaEntity|null
     */
    protected $media;

    /**
     * @var array|null
     */
    protected $attributes;

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function setGroupId(string $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getColorHexCode(): ?string
    {
        return $this->colorHexCode;
    }

    public function setColorHexCode(?string $colorHexCode): void
    {
        $this->colorHexCode = $colorHexCode;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(?string $mediaId): void
    {
        $this->mediaId = $mediaId;
    }

    public function getGroup(): ?ConfigurationGroupEntity
    {
        return $this->group;
    }

    public function setGroup(ConfigurationGroupEntity $group): void
    {
        $this->group = $group;
    }

    public function getTranslations(): ?ConfigurationGroupOptionTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ConfigurationGroupOptionTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getProductConfigurators(): ?ProductConfiguratorCollection
    {
        return $this->productConfigurators;
    }

    public function setProductConfigurators(ProductConfiguratorCollection $productConfigurators): void
    {
        $this->productConfigurators = $productConfigurators;
    }

    public function getProductServices(): ?ProductServiceCollection
    {
        return $this->productServices;
    }

    public function setProductServices(ProductServiceCollection $productServices): void
    {
        $this->productServices = $productServices;
    }

    public function getProductDatasheets(): ?ProductCollection
    {
        return $this->productDatasheets;
    }

    public function setProductDatasheets(ProductCollection $productDatasheets): void
    {
        $this->productDatasheets = $productDatasheets;
    }

    public function getProductVariations(): ?ProductCollection
    {
        return $this->productVariations;
    }

    public function setProductVariations(ProductCollection $productVariations): void
    {
        $this->productVariations = $productVariations;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): void
    {
        $this->position = $position;
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

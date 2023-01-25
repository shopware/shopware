<?php declare(strict_types=1);

namespace Shopware\Core\Content\Property\Aggregate\PropertyGroupOption;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingCollection;
use Shopware\Core\Content\Product\Aggregate\ProductConfiguratorSetting\ProductConfiguratorSettingEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationCollection;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('inventory')]
class PropertyGroupOptionEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string
     */
    protected $groupId;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var int|null
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
     * @var PropertyGroupEntity|null
     */
    protected $group;

    /**
     * @var PropertyGroupOptionTranslationCollection|null
     */
    protected $translations;

    /**
     * @var ProductConfiguratorSettingCollection|null
     */
    protected $productConfiguratorSettings;

    /**
     * @var ProductCollection|null
     */
    protected $productProperties;

    /**
     * @var ProductCollection|null
     */
    protected $productOptions;

    /**
     * @var MediaEntity|null
     */
    protected $media;

    /**
     * @internal
     */
    private ?ProductConfiguratorSettingEntity $configuratorSetting = null;

    /**
     * @internal
     */
    private bool $combinable = false;

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

    public function getGroup(): ?PropertyGroupEntity
    {
        return $this->group;
    }

    public function setGroup(?PropertyGroupEntity $group): void
    {
        $this->group = $group;
    }

    public function getTranslations(): ?PropertyGroupOptionTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(PropertyGroupOptionTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getProductConfiguratorSettings(): ?ProductConfiguratorSettingCollection
    {
        return $this->productConfiguratorSettings;
    }

    public function setProductConfiguratorSettings(ProductConfiguratorSettingCollection $productConfiguratorSettings): void
    {
        $this->productConfiguratorSettings = $productConfiguratorSettings;
    }

    public function getProductProperties(): ?ProductCollection
    {
        return $this->productProperties;
    }

    public function setProductProperties(ProductCollection $productProperties): void
    {
        $this->productProperties = $productProperties;
    }

    public function getProductOptions(): ?ProductCollection
    {
        return $this->productOptions;
    }

    public function setProductOptions(ProductCollection $productOptions): void
    {
        $this->productOptions = $productOptions;
    }

    public function getMedia(): ?MediaEntity
    {
        return $this->media;
    }

    public function setMedia(?MediaEntity $media): void
    {
        $this->media = $media;
    }

    public function getPosition(): ?int
    {
        return $this->position;
    }

    public function setPosition(?int $position): void
    {
        $this->position = $position;
    }

    public function getConfiguratorSetting(): ?ProductConfiguratorSettingEntity
    {
        return $this->configuratorSetting;
    }

    public function setConfiguratorSetting(ProductConfiguratorSettingEntity $configuratorSetting): void
    {
        $this->configuratorSetting = $configuratorSetting;
    }

    public function getCombinable(): bool
    {
        return $this->combinable;
    }

    public function setCombinable(bool $combinable): void
    {
        $this->combinable = $combinable;
    }
}

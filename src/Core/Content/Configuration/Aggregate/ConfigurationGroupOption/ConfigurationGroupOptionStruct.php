<?php declare(strict_types=1);

namespace Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOption;

use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationCollection;
use Shopware\Core\Content\Configuration\ConfigurationGroupStruct;
use Shopware\Core\Content\Product\Aggregate\ProductConfigurator\ProductConfiguratorCollection;
use Shopware\Core\Content\Product\Aggregate\ProductService\ProductServiceCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\ORM\Entity;

class ConfigurationGroupOptionStruct extends Entity
{
    /**
     * @var string
     */
    protected $groupId;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string|null
     */
    protected $colorHexCode;

    /**
     * @var string|null
     */
    protected $mediaId;

    /**
     * @var ConfigurationGroupStruct
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

    public function getGroupId(): string
    {
        return $this->groupId;
    }

    public function setGroupId(string $groupId): void
    {
        $this->groupId = $groupId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
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

    public function getGroup(): ConfigurationGroupStruct
    {
        return $this->group;
    }

    public function setGroup(ConfigurationGroupStruct $group): void
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

    public function setProductConfigurators(?ProductConfiguratorCollection $productConfigurators): void
    {
        $this->productConfigurators = $productConfigurators;
    }

    public function getProductServices(): ?ProductServiceCollection
    {
        return $this->productServices;
    }

    public function setProductServices(?ProductServiceCollection $productServices): void
    {
        $this->productServices = $productServices;
    }

    public function getProductDatasheets(): ?ProductCollection
    {
        return $this->productDatasheets;
    }

    public function setProductDatasheets(?ProductCollection $productDatasheets): void
    {
        $this->productDatasheets = $productDatasheets;
    }

    public function getProductVariations(): ?ProductCollection
    {
        return $this->productVariations;
    }

    public function setProductVariations(?ProductCollection $productVariations): void
    {
        $this->productVariations = $productVariations;
    }
}

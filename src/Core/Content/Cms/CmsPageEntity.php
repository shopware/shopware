<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cms;

use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationEntity;
use Shopware\Core\Content\Cms\Aggregate\CmsSection\CmsSectionCollection;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\LandingPage\LandingPageCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;

#[Package('buyers-experience')]
class CmsPageEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $name;

    /**
     * @var string
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $type;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $entity;

    /**
     * @var CmsSectionCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $sections;

    /**
     * @var EntityCollection<CmsPageTranslationEntity>|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $translations;

    /**
     * @var CategoryCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $categories;

    /**
     * @var ProductCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $products;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $cssClass;

    /**
     * @var array<string, array<string, mixed>>|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $config;

    /**
     * @var string|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $previewMediaId;

    /**
     * @var MediaEntity|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $previewMedia;

    /**
     * @var bool
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $locked;

    /**
     * @var LandingPageCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $landingPages;

    /**
     * @var CmsPageCollection|null
     *
     * @deprecated tag:v6.7.0 - Will be natively typed
     */
    protected $homeSalesChannels;

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getEntity(): ?string
    {
        return $this->entity;
    }

    public function setEntity(?string $entity): void
    {
        $this->entity = $entity;
    }

    public function getSections(): ?CmsSectionCollection
    {
        return $this->sections;
    }

    public function setSections(CmsSectionCollection $sections): void
    {
        $this->sections = $sections;
    }

    /**
     * @return EntityCollection<CmsPageTranslationEntity>|null
     */
    public function getTranslations(): ?EntityCollection
    {
        return $this->translations;
    }

    /**
     * @param EntityCollection<CmsPageTranslationEntity> $translations
     */
    public function setTranslations(EntityCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getCategories(): ?CategoryCollection
    {
        return $this->categories;
    }

    public function setCategories(CategoryCollection $categories): void
    {
        $this->categories = $categories;
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getCssClass(): ?string
    {
        return $this->cssClass;
    }

    public function setCssClass(?string $cssClass): void
    {
        $this->cssClass = $cssClass;
    }

    /**
     * @return array<string, array<string, mixed>>|null
     */
    public function getConfig(): ?array
    {
        return $this->config;
    }

    /**
     * @param array<string, array<string, mixed>> $config
     */
    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function getPreviewMediaId(): ?string
    {
        return $this->previewMediaId;
    }

    public function setPreviewMediaId(string $previewMediaId): void
    {
        $this->previewMediaId = $previewMediaId;
    }

    public function getPreviewMedia(): ?MediaEntity
    {
        return $this->previewMedia;
    }

    public function setPreviewMedia(MediaEntity $previewMedia): void
    {
        $this->previewMedia = $previewMedia;
    }

    public function getLocked(): bool
    {
        return $this->locked;
    }

    public function setLocked(bool $locked): void
    {
        $this->locked = $locked;
    }

    public function getFirstElementOfType(string $type): ?CmsSlotEntity
    {
        $elements = $this->getElementsOfType($type);

        return array_shift($elements);
    }

    public function getLandingPages(): ?LandingPageCollection
    {
        return $this->landingPages;
    }

    public function setLandingPages(LandingPageCollection $landingPages): void
    {
        $this->landingPages = $landingPages;
    }

    public function getHomeSalesChannels(): ?CmsPageCollection
    {
        return $this->homeSalesChannels;
    }

    public function setHomeSalesChannels(CmsPageCollection $homeSalesChannels): void
    {
        $this->homeSalesChannels = $homeSalesChannels;
    }

    /**
     * @return list<CmsSlotEntity>
     */
    public function getElementsOfType(string $type): array
    {
        $elements = [];
        if ($this->getSections() === null) {
            return $elements;
        }

        foreach ($this->getSections()->getBlocks() as $block) {
            if ($block->getSlots() === null) {
                continue;
            }

            foreach ($block->getSlots() as $slot) {
                if ($slot->getType() === $type) {
                    $elements[] = $slot;
                }
            }
        }

        return $elements;
    }

    /**
     * @return list<CmsSlotEntity>
     */
    public function getAllElements(): array
    {
        if ($this->getSections() === null) {
            return [];
        }

        $elements = [];
        foreach ($this->getSections()->getBlocks() as $block) {
            if ($block->getSlots() === null) {
                continue;
            }

            foreach ($block->getSlots() as $slot) {
                $elements[] = $slot;
            }
        }

        return $elements;
    }
}

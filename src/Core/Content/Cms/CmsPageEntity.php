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

#[Package('content')]
class CmsPageEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string|null
     */
    protected $name;

    /**
     * @var string
     */
    protected $type;

    /**
     * @var string|null
     */
    protected $entity;

    /**
     * @var CmsSectionCollection|null
     */
    protected $sections;

    /**
     * @var EntityCollection<CmsPageTranslationEntity>|null
     */
    protected $translations;

    /**
     * @var CategoryCollection|null
     */
    protected $categories;

    /**
     * @var ProductCollection|null
     */
    protected $products;

    /**
     * @var string|null
     */
    protected $cssClass;

    /**
     * @var array|null
     */
    protected $config;

    /**
     * @var string|null
     */
    protected $previewMediaId;

    /**
     * @var MediaEntity|null
     */
    protected $previewMedia;

    /**
     * @var bool
     */
    protected $locked;

    /**
     * @var LandingPageCollection|null
     */
    protected $landingPages;

    /**
     * @var CmsPageCollection|null
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

    public function getConfig(): ?array
    {
        return $this->config;
    }

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
}

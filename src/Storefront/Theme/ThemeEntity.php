<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationCollection;

class ThemeEntity extends Entity
{
    use EntityIdTrait;
    use EntityCustomFieldsTrait;

    /**
     * @var string|null
     */
    protected $technicalName;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $author;

    /**
     * @var string|null
     */
    protected $description;

    /**
     * @var array|null
     */
    protected $labels;

    /**
     * @var array|null
     */
    protected $helpTexts;

    /**
     * @var string|null
     */
    protected $previewMediaId;

    /**
     * @var string|null
     */
    protected $parentThemeId;

    /**
     * @var array|null
     */
    protected $baseConfig;

    /**
     * @var array|null
     */
    protected $configValues;

    /**
     * @var bool
     */
    protected $active;

    /**
     * @var ThemeCollection|null
     */
    protected $childThemes;

    /**
     * @var MediaEntity|null
     */
    protected $previewMedia;

    /**
     * @var MediaCollection|null
     */
    protected $media;

    /**
     * @var SalesChannelCollection|null
     */
    protected $salesChannels;

    /**
     * @var ThemeTranslationCollection|null
     */
    protected $translations;

    public function getTechnicalName(): ?string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(?string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getLabels(): ?array
    {
        return $this->labels;
    }

    public function setLabels(?array $labels): void
    {
        $this->labels = $labels;
    }

    public function getHelpTexts(): ?array
    {
        return $this->helpTexts;
    }

    public function setHelpTexts(?array $helpTexts): void
    {
        $this->helpTexts = $helpTexts;
    }

    public function getPreviewMediaId(): ?string
    {
        return $this->previewMediaId;
    }

    public function setPreviewMediaId(?string $previewMediaId): void
    {
        $this->previewMediaId = $previewMediaId;
    }

    public function getParentThemeId(): ?string
    {
        return $this->parentThemeId;
    }

    public function setParentThemeId(?string $parentThemeId): void
    {
        $this->parentThemeId = $parentThemeId;
    }

    public function getBaseConfig(): ?array
    {
        return $this->baseConfig;
    }

    public function setBaseConfig(?array $baseConfig): void
    {
        $this->baseConfig = $baseConfig;
    }

    public function getConfigValues(): ?array
    {
        return $this->configValues;
    }

    public function setConfigValues(?array $configValues): void
    {
        $this->configValues = $configValues;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): void
    {
        $this->active = $active;
    }

    public function getSalesChannels(): ?SalesChannelCollection
    {
        return $this->salesChannels;
    }

    public function setSalesChannels(SalesChannelCollection $salesChannels): void
    {
        $this->salesChannels = $salesChannels;
    }

    public function getMedia(): ?MediaCollection
    {
        return $this->media;
    }

    public function setMedia(MediaCollection $media): void
    {
        $this->media = $media;
    }

    public function getPreviewMedia(): ?MediaEntity
    {
        return $this->previewMedia;
    }

    public function setPreviewMedia(?MediaEntity $previewMedia): void
    {
        $this->previewMedia = $previewMedia;
    }

    public function getChildThemes(): ?ThemeCollection
    {
        return $this->childThemes;
    }

    public function setChildThemes(ThemeCollection $childThemes): void
    {
        $this->childThemes = $childThemes;
    }

    public function getTranslations(): ?ThemeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ThemeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCustomFieldsTrait;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Storefront\Theme\Aggregate\ThemeTranslationCollection;

#[Package('storefront')]
class ThemeEntity extends Entity
{
    use EntityCustomFieldsTrait;
    use EntityIdTrait;

    protected ?string $technicalName = null;

    protected string $name;

    protected string $author;

    protected ?string $description = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $labels = null;

    /**
     * @var array<string, string>|null
     */
    protected ?array $helpTexts = null;

    protected ?string $previewMediaId = null;

    protected ?string $parentThemeId = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $themeJson = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $baseConfig = null;

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $configValues = null;

    protected bool $active;

    protected ?ThemeCollection $dependentThemes = null;

    protected ?MediaEntity $previewMedia = null;

    protected ?MediaCollection $media = null;

    protected ?SalesChannelCollection $salesChannels = null;

    protected ?ThemeTranslationCollection $translations = null;

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

    /**
     * @return array<string, mixed>|null
     */
    public function getLabels(): ?array
    {
        return $this->labels;
    }

    /**
     * @param array<string, mixed>|null $labels
     */
    public function setLabels(?array $labels): void
    {
        $this->labels = $labels;
    }

    /**
     * @return array<string, string>|null
     */
    public function getHelpTexts(): ?array
    {
        return $this->helpTexts;
    }

    /**
     * @param array<string, string>|null $helpTexts
     */
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

    /**
     * @return array<string, mixed>|null
     */
    public function getThemeJson(): ?array
    {
        return $this->themeJson;
    }

    /**
     * @param array<string, mixed>|null $themeJson
     */
    public function setThemeJson(?array $themeJson): void
    {
        $this->themeJson = $themeJson;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getBaseConfig(): ?array
    {
        return $this->baseConfig;
    }

    /**
     * @param array<string, mixed>|null $baseConfig
     */
    public function setBaseConfig(?array $baseConfig): void
    {
        $this->baseConfig = $baseConfig;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getConfigValues(): ?array
    {
        return $this->configValues;
    }

    /**
     * @param array<string, mixed>|null $configValues
     */
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

    public function getTranslations(): ?ThemeTranslationCollection
    {
        return $this->translations;
    }

    public function setTranslations(ThemeTranslationCollection $translations): void
    {
        $this->translations = $translations;
    }

    public function getDependentThemes(): ?ThemeCollection
    {
        return $this->dependentThemes;
    }

    public function setDependentThemes(ThemeCollection $dependentThemes): void
    {
        $this->dependentThemes = $dependentThemes;
    }
}

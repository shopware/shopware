<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

class StorefrontPluginConfiguration
{
    /**
     * @var array
     */
    private $themeConfig = [];

    /**
     * @var string
     */
    private $technicalName;

    /**
     * @var string
     */
    private $name;

    /**
     * @var string|null
     */
    private $previewMedia;

    /**
     * @var string|null
     */
    private $author;

    /**
     * @var bool|null
     */
    private $isTheme;

    /**
     * @var FileCollection
     */
    private $styleFiles;

    /**
     * @var FileCollection
     */
    private $scriptFiles;

    /**
     * @var string|null
     */
    private $storefrontEntryFilepath;

    /**
     * @var string|null
     */
    private $basePath;

    /**
     * @var array
     */
    private $assetPaths = [];

    /**
     * @var string[]
     */
    private $viewInheritance = [];

    public function getTechnicalName(): ?string
    {
        return $this->technicalName;
    }

    public function setTechnicalName(string $technicalName): void
    {
        $this->technicalName = $technicalName;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getAuthor(): ?string
    {
        return $this->author;
    }

    public function setAuthor(?string $author): void
    {
        $this->author = $author;
    }

    public function getIsTheme(): ?bool
    {
        return $this->isTheme;
    }

    public function setIsTheme(bool $isTheme): void
    {
        $this->isTheme = $isTheme;
    }

    public function getStyleFiles(): FileCollection
    {
        return $this->styleFiles;
    }

    public function setStyleFiles(FileCollection $styleFiles): void
    {
        $this->styleFiles = $styleFiles;
    }

    public function getScriptFiles(): FileCollection
    {
        return $this->scriptFiles;
    }

    public function setScriptFiles(FileCollection $scriptFiles): void
    {
        $this->scriptFiles = $scriptFiles;
    }

    public function getStorefrontEntryFilepath(): ?string
    {
        return $this->storefrontEntryFilepath;
    }

    public function setStorefrontEntryFilepath(?string $storefrontEntryFilepath): void
    {
        $this->storefrontEntryFilepath = $storefrontEntryFilepath;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    public function getAssetPaths(): array
    {
        return $this->assetPaths;
    }

    public function setAssetPaths(array $assetPaths): void
    {
        $this->assetPaths = $assetPaths;
    }

    public function getThemeConfig(): ?array
    {
        return $this->themeConfig;
    }

    public function setThemeConfig(?array $themeConfig): void
    {
        $this->themeConfig = $themeConfig;
    }

    public function getPreviewMedia(): ?string
    {
        return $this->previewMedia;
    }

    public function setPreviewMedia(string $previewMedia): void
    {
        $this->previewMedia = $previewMedia;
    }

    /**
     * @return string[]
     */
    public function getViewInheritance(): array
    {
        return $this->viewInheritance;
    }

    /**
     * @param string[] $viewInheritance
     */
    public function setViewInheritance(array $viewInheritance): void
    {
        $this->viewInheritance = $viewInheritance;
    }

    public function hasFilesToCompile(): bool
    {
        return \count($this->getStyleFiles()) !== 0 || \count($this->getScriptFiles()) !== 0;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Struct\Struct;

class StorefrontPluginConfiguration extends Struct
{
    protected ?array $themeConfig = [];

    protected string $technicalName;

    protected ?string $name = null;

    protected ?string $previewMedia = null;

    protected ?string $author = null;

    protected ?bool $isTheme = null;

    protected FileCollection $styleFiles;

    protected FileCollection $scriptFiles;

    protected ?string $storefrontEntryFilepath = null;

    protected ?string $basePath = null;

    protected array $assetPaths = [];

    /**
     * @var string[]
     */
    protected array $viewInheritance = [];

    /**
     * @var array<string, string>
     */
    protected array $iconSets = [];

    public function __construct(string $technicalName)
    {
        $this->technicalName = $technicalName;
        $this->styleFiles = new FileCollection();
        $this->scriptFiles = new FileCollection();
    }

    public function getTechnicalName(): string
    {
        return $this->technicalName;
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

    /**
     * @return array<string, string>
     */
    public function getIconSets(): array
    {
        return $this->iconSets;
    }

    /**
     * @param array<string, string> $iconSets
     */
    public function setIconSets(array $iconSets): void
    {
        $this->iconSets = $iconSets;
    }

    public function hasFilesToCompile(): bool
    {
        return \count($this->getStyleFiles()) !== 0 || \count($this->getScriptFiles()) !== 0;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Bundle;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Symfony\Component\Finder\Finder;

class StorefrontPluginConfiguration
{
    /**
     * @var array
     */
    protected $config = [];

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
    private $author;

    /**
     * @var bool|null
     */
    private $isTheme;

    /**
     * @var array
     */
    private $styleFiles = [];

    /**
     * @var array
     */
    private $entries = [];

    /**
     * @var array
     */
    private $scriptFiles = [];

    /**
     * @var string|null
     */
    private $basePath;

    /**
     * @var array
     */
    private $assetFiles = [];

    /**
     * @var string
     */
    private $themeVariableFile;

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

    public function getStyleFiles(): array
    {
        return $this->styleFiles;
    }

    public function setStyleFiles(array $styleFiles): void
    {
        $this->styleFiles = $styleFiles;
    }

    public function addStyleFile(string $cssFile, ?string $group = 'unsorted'): void
    {
        $this->styleFiles[$group][] = $cssFile;
    }

    public function getEntries(): array
    {
        return $this->entries;
    }

    public function setEntries(array $entries): void
    {
        $this->entries = $entries;
    }

    public function getScriptFiles(): array
    {
        return $this->scriptFiles;
    }

    public function setScriptFiles(array $scriptFiles): void
    {
        $this->scriptFiles = $scriptFiles;
    }

    public function addScriptFile(string $jsFile): void
    {
        $this->scriptFiles[] = $jsFile;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    public function setBasePath(string $basePath): void
    {
        $this->basePath = $basePath;
    }

    public function getAssetFiles(): array
    {
        return $this->assetFiles;
    }

    public function setAssetFiles(array $assetFiles): void
    {
        $this->assetFiles = $assetFiles;
    }

    public function getConfig(): ?array
    {
        return $this->config;
    }

    public function setConfig(?array $config): void
    {
        $this->config = $config;
    }

    public function getThemeVariableFile(): string
    {
        return $this->themeVariableFile;
    }

    public function setThemeVariableFile(string $themeVariableFile): void
    {
        $this->themeVariableFile = $themeVariableFile;
    }

    public static function createFromBundle(Bundle $bundle): self
    {
        $config = new self();
        $config->setIsTheme(false);
        $config->setTechnicalName($bundle->getName());
        $config->setBasePath($bundle->getPath());
        if ($bundle->getStorefrontStylePath()) {
            $path = $bundle->getPath() . DIRECTORY_SEPARATOR . ltrim($bundle->getStorefrontStylePath(), DIRECTORY_SEPARATOR);
            $config->setStyleFiles(self::getFilesInDir($path));
        }

        if ($bundle->getStorefrontScriptPath()) {
            $path = $bundle->getPath() . DIRECTORY_SEPARATOR . ltrim($bundle->getStorefrontScriptPath(), DIRECTORY_SEPARATOR);
            $config->setScriptFiles(self::getFilesInDir($path));
        }

        return $config;
    }

    public static function createFromConfigFile(string $pathname, Bundle $bundle): self
    {
        $config = new self();
        try {
            $data = json_decode(file_get_contents($pathname), true);
            $basePath = realpath(pathinfo($pathname, PATHINFO_DIRNAME));

            $config->setBasePath($basePath);
            $config->setTechnicalName($bundle->getName());
            $config->setIsTheme(true);
            $config->setName($data['name']);
            $config->setAuthor($data['author']);

            if (array_key_exists('style', $data) && is_array($data['style'])) {
                $config->setStyleFiles(self::addBasePath($data['style'], $basePath));
            }

            if (array_key_exists('entries', $data)) {
                $config->setEntries(self::addBasePath($data['entries'], $basePath));
            }

            if (array_key_exists('script', $data) && is_array($data['script'])) {
                $config->setScriptFiles(self::addBasePath($data['script'], $basePath));
            }

            if (array_key_exists('asset', $data)) {
                $config->setAssetFiles(self::addBasePath($data['asset'], $basePath));
            }

            if (array_key_exists('config', $data)) {
                $config->setConfig($data['config']);
            }
        } catch (\Exception $e) {
            throw new ThemeCompileException($pathname,
                sprintf(
                    'Got exception while parsing theme config. Exception message "%s"',
                    $e->getMessage()
                )
            );
        }

        return $config;
    }

    private static function addBasePath(array $files, string $basePath): array
    {
        array_walk($files, function (&$path) use ($basePath) {
            if (strpos($path, '@') === 0) {
                return;
            }
            $path = $basePath . DIRECTORY_SEPARATOR . $path;
        });

        return $files;
    }

    private static function getFilesInDir(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }
        $finder = new Finder();
        $finder->files()->in($path);

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }
}

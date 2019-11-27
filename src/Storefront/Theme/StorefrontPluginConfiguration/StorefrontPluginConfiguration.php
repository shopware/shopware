<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Bundle;
use Shopware\Storefront\Framework\ThemeInterface;
use Shopware\Storefront\Theme\Exception\InvalidThemeBundleException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Symfony\Component\Finder\Finder;

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

    public function getThemeVariableFile(): string
    {
        return $this->themeVariableFile;
    }

    public function setThemeVariableFile(string $themeVariableFile): void
    {
        $this->themeVariableFile = $themeVariableFile;
    }

    public function getPreviewMedia(): ?string
    {
        return $this->previewMedia;
    }

    public function setPreviewMedia(string $previewMedia): void
    {
        $this->previewMedia = $previewMedia;
    }

    public static function createFromBundle(Bundle $bundle): self
    {
        $config = new self();
        $config->setIsTheme(false);
        $config->setTechnicalName($bundle->getName());
        $config->setStorefrontEntryFilepath(self::getEntryFile($bundle));
        $config->setBasePath($bundle->getPath());

        $path = $bundle->getPath() . DIRECTORY_SEPARATOR . 'Resources/app/storefront/src/scss';
        $config->setStyleFiles(FileCollection::createFromArray(self::getFilesInDir($path)));

        $path = $bundle->getPath() . DIRECTORY_SEPARATOR . 'Resources/app/storefront/dist/storefront/js';
        $config->setScriptFiles(FileCollection::createFromArray(self::getFilesInDir($path)));

        return $config;
    }

    public static function createFromConfigFile(Bundle $bundle): self
    {
        if (!($bundle instanceof ThemeInterface)) {
            throw new InvalidThemeBundleException($bundle->getName());
        }
        $pathname = $bundle->getPath() . DIRECTORY_SEPARATOR . 'Resources/theme.json';

        if (!file_exists($pathname)) {
            throw new InvalidThemeBundleException($bundle->getName());
        }

        $config = new self();

        try {
            $data = json_decode(file_get_contents($pathname), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new ThemeCompileException(
                    $bundle->getName(),
                    'Unable to parse theme.json. Message: ' . json_last_error_msg()
                );
            }

            $basePath = realpath(pathinfo($pathname, PATHINFO_DIRNAME));

            $config->setBasePath($basePath);
            $config->setTechnicalName($bundle->getName());
            $config->setStorefrontEntryFilepath(self::getEntryFile($bundle));
            $config->setIsTheme(true);
            $config->setName($data['name']);
            $config->setAuthor($data['author']);

            if (array_key_exists('style', $data) && is_array($data['style'])) {
                $fileCollection = new FileCollection();
                foreach ($data['style'] as $style) {
                    if (!is_array($style)) {
                        $fileCollection->add(new File($style));

                        continue;
                    }

                    foreach ($style as $filename => $additional) {
                        if (!array_key_exists('resolve', $additional)) {
                            $fileCollection->add(new File($filename));
                        }

                        foreach ($additional['resolve'] as $resolve => &$path) {
                            $path = self::addBasePath($path, $basePath);
                        }
                        unset($path);

                        $fileCollection->add(new File($filename, $additional['resolve'] ?? []));
                    }
                }
                $config->setStyleFiles(self::addBasePathToCollection($fileCollection, $basePath));
            }

            if (array_key_exists('script', $data) && is_array($data['script'])) {
                $fileCollection = FileCollection::createFromArray($data['script']);
                $config->setScriptFiles(self::addBasePathToCollection($fileCollection, $basePath));
            }

            if (array_key_exists('asset', $data)) {
                $config->setAssetPaths(self::addBasePathToArray($data['asset'], $basePath));
            }

            if (array_key_exists('previewMedia', $data)) {
                $config->setPreviewMedia($basePath . DIRECTORY_SEPARATOR . $data['previewMedia']);
            }

            if (array_key_exists('config', $data)) {
                $config->setThemeConfig($data['config']);
            }
        } catch (ThemeCompileException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ThemeCompileException(
                $bundle->getName(),
                sprintf(
                    'Got exception while parsing theme config. Exception message "%s"',
                    $e->getMessage()
                )
            );
        }

        return $config;
    }

    private static function getEntryFile(Bundle $bundle): ?string
    {
        $path = rtrim($bundle->getPath(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'Resources/app/storefront/src';

        if (file_exists($path . DIRECTORY_SEPARATOR . 'main.ts')) {
            return $path . DIRECTORY_SEPARATOR . 'main.ts';
        }

        if (file_exists($path . DIRECTORY_SEPARATOR . 'main.js')) {
            return $path . DIRECTORY_SEPARATOR . 'main.js';
        }

        return null;
    }

    private static function addBasePathToCollection(FileCollection $fileCollection, string $basePath): FileCollection
    {
        foreach ($fileCollection as $file) {
            if (mb_strpos($file->getFilepath(), '@') === 0) {
                continue;
            }
            $file->setFilepath(self::addBasePath($file->getFilepath(), $basePath));
        }

        return $fileCollection;
    }

    private static function addBasePathToArray(array $files, string $basePath): array
    {
        array_walk($files, function (&$path) use ($basePath): void {
            if (mb_strpos($path, '@') === 0) {
                return;
            }
            $path = self::addBasePath($path, $basePath);
        });

        return $files;
    }

    private static function addBasePath(string $path, string $basePath): string
    {
        return $basePath . DIRECTORY_SEPARATOR . $path;
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

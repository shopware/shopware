<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Storefront\Framework\ThemeInterface;
use Shopware\Storefront\Theme\Exception\InvalidThemeBundleException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Symfony\Component\Finder\Finder;

class StorefrontPluginConfigurationFactory extends AbstractStorefrontPluginConfigurationFactory
{
    /**
     * @var string
     */
    private $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    public function getDecorated(): AbstractStorefrontPluginConfigurationFactory
    {
        throw new DecorationPatternException(self::class);
    }

    public function createFromBundle(Bundle $bundle): StorefrontPluginConfiguration
    {
        if ($bundle instanceof ThemeInterface) {
            return $this->createThemeConfig($bundle->getName(), $bundle->getPath());
        }

        return $this->createPluginConfig($bundle->getName(), $bundle->getPath());
    }

    public function createFromApp(string $appName, string $appPath): StorefrontPluginConfiguration
    {
        $absolutePath = $this->projectDir . '/' . $appPath;
        if (file_exists($absolutePath . '/Resources/theme.json')) {
            return $this->createThemeConfig($appName, $absolutePath);
        }

        return $this->createPluginConfig($appName, $absolutePath);
    }

    private function createPluginConfig(string $name, string $path): StorefrontPluginConfiguration
    {
        $config = new StorefrontPluginConfiguration($name);
        $config->setIsTheme(false);
        $config->setStorefrontEntryFilepath($this->getEntryFile($path));
        $config->setBasePath($path);

        $stylesPath = $path . \DIRECTORY_SEPARATOR . 'Resources/app/storefront/src/scss';
        $config->setStyleFiles(FileCollection::createFromArray($this->getScssEntryFileInDir($stylesPath)));

        $scriptPath = $path . \DIRECTORY_SEPARATOR . 'Resources/app/storefront/dist/storefront/js';
        $config->setScriptFiles(FileCollection::createFromArray($this->getFilesInDir($scriptPath)));

        return $config;
    }

    private function createThemeConfig(string $name, string $path): StorefrontPluginConfiguration
    {
        $pathname = $path . \DIRECTORY_SEPARATOR . 'Resources/theme.json';

        if (!file_exists($pathname)) {
            throw new InvalidThemeBundleException($name);
        }

        $config = new StorefrontPluginConfiguration($name);

        try {
            $data = json_decode(file_get_contents($pathname), true);
            if (json_last_error() !== \JSON_ERROR_NONE) {
                throw new ThemeCompileException(
                    $name,
                    'Unable to parse theme.json. Message: ' . json_last_error_msg()
                );
            }

            $basePath = realpath(pathinfo($pathname, \PATHINFO_DIRNAME));

            $config->setBasePath($basePath);
            $config->setStorefrontEntryFilepath($this->getEntryFile($path));
            $config->setIsTheme(true);
            $config->setName($data['name']);
            $config->setAuthor($data['author']);

            if (\array_key_exists('style', $data) && \is_array($data['style'])) {
                $fileCollection = new FileCollection();
                foreach ($data['style'] as $style) {
                    if (!\is_array($style)) {
                        $fileCollection->add(new File($style));

                        continue;
                    }

                    foreach ($style as $filename => $additional) {
                        if (!\array_key_exists('resolve', $additional)) {
                            $fileCollection->add(new File($filename));

                            continue;
                        }

                        foreach ($additional['resolve'] as &$resolvePath) {
                            $resolvePath = $this->addBasePath($resolvePath, $basePath);
                        }
                        unset($resolvePath);

                        $fileCollection->add(new File($filename, $additional['resolve'] ?? []));
                    }
                }
                $config->setStyleFiles($this->addBasePathToCollection($fileCollection, $basePath));
            }

            if (\array_key_exists('script', $data) && \is_array($data['script'])) {
                $fileCollection = FileCollection::createFromArray($data['script']);
                $config->setScriptFiles($this->addBasePathToCollection($fileCollection, $basePath));
            }

            if (\array_key_exists('asset', $data)) {
                $config->setAssetPaths($this->addBasePathToArray($data['asset'], $basePath));
            }

            if (\array_key_exists('previewMedia', $data)) {
                $config->setPreviewMedia($basePath . \DIRECTORY_SEPARATOR . $data['previewMedia']);
            }

            if (\array_key_exists('config', $data)) {
                $config->setThemeConfig($data['config']);
            }

            if (\array_key_exists('views', $data)) {
                $config->setViewInheritance($data['views']);
            }

            if (\array_key_exists('iconSets', $data)) {
                $config->setIconSets($data['iconSets']);
            }
        } catch (ThemeCompileException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ThemeCompileException(
                $name,
                sprintf(
                    'Got exception while parsing theme config. Exception message "%s"',
                    $e->getMessage()
                )
            );
        }

        return $config;
    }

    private function getEntryFile(string $path): ?string
    {
        $path = rtrim($path, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR . 'Resources/app/storefront/src';

        if (file_exists($path . \DIRECTORY_SEPARATOR . 'main.ts')) {
            return $path . \DIRECTORY_SEPARATOR . 'main.ts';
        }

        if (file_exists($path . \DIRECTORY_SEPARATOR . 'main.js')) {
            return $path . \DIRECTORY_SEPARATOR . 'main.js';
        }

        return null;
    }

    private function addBasePathToCollection(FileCollection $fileCollection, string $basePath): FileCollection
    {
        foreach ($fileCollection as $file) {
            if (mb_strpos($file->getFilepath(), '@') === 0) {
                continue;
            }
            $file->setFilepath($this->addBasePath($file->getFilepath(), $basePath));
        }

        return $fileCollection;
    }

    private function addBasePathToArray(array $files, string $basePath): array
    {
        array_walk($files, function (&$path) use ($basePath): void {
            if (mb_strpos($path, '@') === 0) {
                return;
            }
            $path = self::addBasePath($path, $basePath);
        });

        return $files;
    }

    private function addBasePath(string $path, string $basePath): string
    {
        return $basePath . \DIRECTORY_SEPARATOR . $path;
    }

    private function getFilesInDir(string $path): array
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

    private function getScssEntryFileInDir(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }
        $finder = new Finder();
        $finder->files()->name('base.scss')->in($path)->depth('0');

        $files = [];
        foreach ($finder as $file) {
            $files[] = $file->getPathname();
        }

        return $files;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\App\Source\SourceResolver;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Plugin\KernelPluginLoader\KernelPluginLoader;
use Shopware\Storefront\Framework\ThemeInterface;
use Shopware\Storefront\Theme\Exception\InvalidThemeBundleException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Symfony\Component\Finder\Finder;

#[Package('storefront')]
class StorefrontPluginConfigurationFactory extends AbstractStorefrontPluginConfigurationFactory
{
    /**
     * @internal
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly KernelPluginLoader $pluginLoader,
        private readonly SourceResolver $sourceResolver
    ) {
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

        $config = $this->createPluginConfig($bundle->getName(), $bundle->getPath());
        if ($bundle instanceof Plugin) {
            $config->setAdditionalBundles(
                !empty(
                    $bundle->getAdditionalBundles(
                        new AdditionalBundleParameters(
                            $this->pluginLoader->getClassLoader(),
                            $this->pluginLoader->getPluginInstances(),
                            []
                        )
                    )
                )
            );
        }

        return $config;
    }

    public function createFromApp(string $appName, string $appPath): StorefrontPluginConfiguration
    {
        $fs = $this->sourceResolver->filesystemForAppName($appName);

        if ($fs->has('Resources/theme.json')) {
            return $this->createThemeConfig($appName, $fs->path());
        }

        return $this->createPluginConfig($appName, $fs->path());
    }

    /**
     * @param array<string, mixed> $data
     */
    public function createFromThemeJson(string $name, array $data, string $path, bool $isFullpath = true): StorefrontPluginConfiguration
    {
        try {
            if (!$isFullpath) {
                $path = $this->projectDir . \DIRECTORY_SEPARATOR . str_replace(\DIRECTORY_SEPARATOR . 'Resources', '', $path);
            }

            $config = new StorefrontPluginConfiguration($name);

            $config->setThemeJson($data);
            $config->setBasePath($this->stripProjectDir($path));
            $config->setStorefrontEntryFilepath($this->getEntryFile($path));
            $config->setIsTheme(true);
            $config->setName($data['name']);
            $config->setAuthor($data['author']);

            if (\array_key_exists('style', $data) && \is_array($data['style'])) {
                $this->resolveStyleFiles($data['style'], $config);
            }

            if (\array_key_exists('script', $data) && \is_array($data['script'])) {
                $fileCollection = FileCollection::createFromArray($data['script']);
                $config->setScriptFiles($fileCollection);
            }

            if (\array_key_exists('asset', $data)) {
                $config->setAssetPaths($data['asset']);
            }

            if (\array_key_exists('previewMedia', $data)) {
                $config->setPreviewMedia($data['previewMedia']);
            }

            if (\array_key_exists('config', $data)) {
                $config->setThemeConfig($data['config']);
            }

            if (\array_key_exists('views', $data)) {
                $config->setViewInheritance($data['views']);
            }

            if (\array_key_exists('configInheritance', $data)) {
                $config->setConfigInheritance($data['configInheritance']);
                $baseConfig = $config->getThemeConfig();
                $baseConfig['configInheritance'] = $data['configInheritance'];
                $config->setThemeConfig($baseConfig);
            }

            if (\array_key_exists('iconSets', $data)) {
                $config->setIconSets($data['iconSets']);
            }
        } catch (\Throwable) {
            $config = new StorefrontPluginConfiguration($name);
        }

        return $config;
    }

    private function createPluginConfig(string $name, string $path): StorefrontPluginConfiguration
    {
        $config = new StorefrontPluginConfiguration($name);
        $config->setIsTheme(false);
        $config->setStorefrontEntryFilepath($this->getEntryFile($path));
        $config->setBasePath($this->stripProjectDir($path));

        $stylesPath = $path . '/Resources/app/storefront/src/scss';
        $config->setStyleFiles(FileCollection::createFromArray($this->getScssEntryFileInDir($stylesPath, $path . '/Resources')));

        $assetName = $config->getAssetName();

        $scriptPath = $path . \sprintf('/Resources/app/storefront/dist/storefront/js/%s/%s.js', $assetName, $assetName);

        if (file_exists($scriptPath)) {
            $config->setScriptFiles(FileCollection::createFromArray([$this->stripBasePath($scriptPath, $path . '/Resources')]));

            return $config;
        }

        return $config;
    }

    private function createThemeConfig(string $name, string $path): StorefrontPluginConfiguration
    {
        $pathname = $path . \DIRECTORY_SEPARATOR . 'Resources/theme.json';

        if (!file_exists($pathname)) {
            throw new InvalidThemeBundleException($name);
        }

        try {
            $fileContent = file_get_contents($pathname);
            if ($fileContent === false) {
                throw new ThemeCompileException(
                    $name,
                    'Unable to read theme.json'
                );
            }

            /** @var array<string, mixed> $data */
            $data = json_decode($fileContent, true);
            if (json_last_error() !== \JSON_ERROR_NONE) {
                throw new ThemeCompileException(
                    $name,
                    'Unable to parse theme.json. Message: ' . json_last_error_msg()
                );
            }

            $config = $this->createFromThemeJson($name, $data, $path);
        } catch (ThemeCompileException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new ThemeCompileException(
                $name,
                \sprintf(
                    'Got exception while parsing theme config. Exception message "%s"',
                    $e->getMessage()
                ),
                $e
            );
        }

        return $config;
    }

    private function getEntryFile(string $path): ?string
    {
        $path = rtrim($path, '/') . '/Resources/app/storefront/src';

        if (file_exists($path . '/main.ts')) {
            return 'app/storefront/src/main.ts';
        }

        if (file_exists($path . '/main.js')) {
            return 'app/storefront/src/main.js';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    private function getScssEntryFileInDir(string $path, string $basePath): array
    {
        if (!is_dir($path)) {
            return [];
        }
        $finder = new Finder();
        $finder->files()->name('base.scss')->in($path)->depth('0');

        $files = [];
        foreach ($finder as $file) {
            $files[] = $this->stripBasePath($file->getPathname(), $basePath);
        }

        return $files;
    }

    private function stripBasePath(string $path, string $basePath): string
    {
        if (str_starts_with($path, $basePath)) {
            return substr($path, \strlen($basePath) + 1);
        }

        return $path;
    }

    private function stripProjectDir(string $path): string
    {
        return $this->stripBasePath($path, $this->projectDir);
    }

    /**
     * @param array<string|array<array{resolve?: array<string, string>}>> $styles
     */
    private function resolveStyleFiles(array $styles, StorefrontPluginConfiguration $config): void
    {
        $fileCollection = new FileCollection();
        foreach ($styles as $style) {
            if (!\is_array($style)) {
                $fileCollection->add(new File($style));

                continue;
            }

            foreach ($style as $filename => $additional) {
                if (!\array_key_exists('resolve', $additional)) {
                    $fileCollection->add(new File($filename));

                    continue;
                }

                $fileCollection->add(new File($filename, $additional['resolve'] ?? []));
            }
        }
        $config->setStyleFiles($fileCollection);
    }
}

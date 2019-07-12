<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemInterface;
use ScssPhp\ScssPhp\Compiler;
use Shopware\Storefront\Theme\Exception\InvalidThemeException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

class ThemeCompiler
{
    /**
     * @var FilesystemInterface
     */
    private $publicFilesystem;

    /**
     * @var Filesystem
     */
    private $localFileSystem;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var Compiler
     */
    private $scssCompiler;

    public function __construct(
        FilesystemInterface $publicFilesystem,
        Filesystem $localFileSystem,
        string $cacheDir
    ) {
        $this->publicFilesystem = $publicFilesystem;
        $this->localFileSystem = $localFileSystem;
        $this->cacheDir = $cacheDir;

        $this->scssCompiler = new Compiler();
        $this->scssCompiler->setImportPaths('');
    }

    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection
    ): void {
        $themePrefix = self::getThemePrefix($salesChannelId, $themeId);
        $outputPath = 'theme' . DIRECTORY_SEPARATOR . $themePrefix;

        if (is_dir('theme' . DIRECTORY_SEPARATOR . $themePrefix)) {
            $this->publicFilesystem->deleteDir($outputPath);
        }

        // styles
        $compiled = $this->compileStyles($themeConfig, $configurationCollection);
        $cssFilepath = $outputPath . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'all.css';
        $this->publicFilesystem->put($cssFilepath, $compiled);

        // scripts
        $scriptFile = $this->concatenateScripts($themeConfig->getScriptFiles(), $configurationCollection);
        $scriptFilepath = $outputPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'all.js';
        $this->publicFilesystem->put($scriptFilepath, $scriptFile);

        // assets
        $this->copyAssets($themeConfig, $configurationCollection, $outputPath);
    }

    public static function getThemePrefix(string $salesChannelId, string $themeId): string
    {
        return md5($themeId . $salesChannelId);
    }

    private function copyAssets(
        StorefrontPluginConfiguration $configuration,
        StorefrontPluginConfigurationCollection $configurationCollection,
        string $outputPath
    ) {
        if (!$configuration->getAssetFiles()) {
            return;
        }

        foreach ($configuration->getAssetFiles() as $asset) {
            if (strpos($asset, '@') === 0) {
                $name = substr($asset, 1);
                $config = $configurationCollection->getByTechnicalName($name);
                if (!$config) {
                    throw new InvalidThemeException($name);
                }

                $this->copyAssets($config, $configurationCollection, $outputPath);
                continue;
            }

            if (!is_dir($asset)) {
                throw new ThemeCompileException(
                    $configuration->getTechnicalName(),
                    sprintf('Unable to find asset. Path: "%s"', $asset)
                );
            }

            $finder = new Finder();
            $files = $finder->files()->in($asset);

            foreach ($files as $file) {
                $relativePathname = $file->getRelativePathname();
                $assetDir = basename($asset);

                $content = file_get_contents($asset . DIRECTORY_SEPARATOR . $relativePathname);

                $this->publicFilesystem->put(
                    'bundles' . DIRECTORY_SEPARATOR . strtolower($configuration->getTechnicalName()) . DIRECTORY_SEPARATOR . $assetDir . DIRECTORY_SEPARATOR . $relativePathname,
                    $content
                );

                $this->publicFilesystem->put(
                    $outputPath . DIRECTORY_SEPARATOR . $assetDir . DIRECTORY_SEPARATOR . $relativePathname,
                    $content
                );
            }
        }
    }

    private function concatenateScripts(
        array $jsFiles,
        StorefrontPluginConfigurationCollection $configurationCollection
    ): string {
        $output = '';

        foreach ($jsFiles as $jsFile) {
            $output .= $this->resolveScripts($jsFile, $configurationCollection);
        }

        return $output;
    }

    private function resolveScripts(
        string $scriptFile,
        StorefrontPluginConfigurationCollection $configurationCollection
    ): string {
        if (strpos($scriptFile, '@') !== 0) {
            if (file_exists($scriptFile)) {
                return file_get_contents($scriptFile) . PHP_EOL;
            }
            throw new ThemeCompileException('', sprintf('Unable to load script file "%s". Did you forget to build the theme? Try running ./psh.phar storefront:build ', $scriptFile));
        }

        if ($scriptFile === '@Plugins') {
            $output = '';
            foreach ($configurationCollection->getNoneThemes() as $plugin) {
                $output .= $this->concatenateScripts($plugin->getScriptFiles(), $configurationCollection);
            }

            return $output;
        }

        // Resolve @ dependencies
        $name = substr($scriptFile, 1);
        $config = $configurationCollection->getByTechnicalName($name);

        if (!$config) {
            throw new InvalidThemeException($name);
        }

        return $this->concatenateScripts($config->getScriptFiles(), $configurationCollection);
    }

    private function concatenateCss(
        array $cssFiles,
        StorefrontPluginConfigurationCollection $configurationCollection,
        StorefrontPluginConfiguration $config
    ): string {
        $output = '';
        foreach ($cssFiles as $cssFile) {
            $output .= $this->resolveCss($cssFile, $configurationCollection, $config);
        }

        return $output;
    }

    private function resolveCss(
        string $cssFile,
        StorefrontPluginConfigurationCollection $configurationCollection,
        StorefrontPluginConfiguration $originalConfig
    ): string {
        if (strpos($cssFile, '@') !== 0) {
            $this->scssCompiler->addImportPath(dirname($cssFile));

            return file_get_contents($cssFile) . PHP_EOL;
        }

        if ($cssFile === '@Plugins') {
            $output = '';
            foreach ($configurationCollection->getNoneThemes() as $plugin) {
                $output .= $this->concatenateCss($plugin->getStyleFiles(), $configurationCollection, $originalConfig);
            }

            return $output;
        }

        $name = substr($cssFile, 1);
        $config = $configurationCollection->getByTechnicalName($name);

        if (!$config) {
            throw new InvalidThemeException($name);
        }

        $originalConfig->setEntries(array_replace_recursive($originalConfig->getEntries(), $config->getEntries()));

        return $this->concatenateCss($config->getStyleFiles(), $configurationCollection, $originalConfig);
    }

    private function compileStyles(
        StorefrontPluginConfiguration $config,
        StorefrontPluginConfigurationCollection $configurationCollection
    ): string {
        $concatenated = $this->concatenateCss($config->getStyleFiles(), $configurationCollection, $config);

        //$this->scssCompiler->setImportPaths('');

        foreach ($config->getStyleFiles() as $styleFile) {
            $this->scssCompiler->addImportPath(dirname($styleFile));
        }

        $this->scssCompiler->addImportPath(function ($originalPath) use ($config) {
            foreach ($config->getEntries() as $entry => $entryPath) {
                $entry = '~' . $entry;
                if (strpos($originalPath, $entry) === 0) {
                    $dirname = $entryPath . dirname(substr($originalPath, strlen($entry)));
                    $filename = basename($originalPath);
                    $extension = pathinfo($filename, PATHINFO_EXTENSION) === '' ? '.scss' : '';
                    $path = $dirname . DIRECTORY_SEPARATOR . $filename . $extension;
                    if (file_exists($path)) {
                        return $path;
                    }

                    $path = $dirname . DIRECTORY_SEPARATOR . '_' . $filename . $extension;
                    if (file_exists($path)) {
                        return $path;
                    }
                }
            }

            return null;
        });

        $variables = $this->dumpVariables($config->getConfig());

        return $this->scssCompiler->compile($variables . $concatenated);
    }

    private function dumpVariables(array $config): string
    {
        if (!array_key_exists('fields', $config)) {
            return '';
        }

        $variables = [];
        foreach ($config['fields'] as $key => $data) {
            if (array_key_exists('value', $data) && $data['value']) {
                if ($data['type'] === 'media') {
                    $variables[] = sprintf('$%s: \'%s\';', $key, $data['value']);
                } else {
                    $variables[] = sprintf('$%s: %s;', $key, $data['value']);
                }
            }
        }

        $dump = str_replace(
            ['#class#', '#variables#'],
            [self::class, implode(PHP_EOL, $variables)],
            $this->getVariableDumpTemplate()
        );

        file_put_contents(
            $this->cacheDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'theme-variables.scss',
            $dump
        );

        return $dump;
    }

    private function getVariableDumpTemplate(): string
    {
        return <<<PHP_EOL
// ATTENTION! This file is auto generated by the #class# and should not be edited.

#variables#

PHP_EOL;
    }
}

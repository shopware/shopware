<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemInterface;
use Padaliyajay\PHPAutoprefixer\Autoprefixer;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Formatter\Crunched;
use ScssPhp\ScssPhp\Formatter\Expanded;
use Shopware\Storefront\Theme\Exception\InvalidThemeException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Component\Finder\Finder;

class ThemeCompiler
{
    /**
     * @var FilesystemInterface
     */
    private $publicFilesystem;

    /**
     * @var string
     */
    private $cacheDir;

    /**
     * @var Compiler
     */
    private $scssCompiler;

    /**
     * @var ThemeFileResolver
     */
    private $themeFileResolver;

    public function __construct(
        FilesystemInterface $publicFilesystem,
        ThemeFileResolver $themeFileResolver,
        string $cacheDir,
        bool $debug
    ) {
        $this->publicFilesystem = $publicFilesystem;
        $this->themeFileResolver = $themeFileResolver;
        $this->cacheDir = $cacheDir;

        $this->scssCompiler = new Compiler();
        $this->scssCompiler->setImportPaths('');

        $this->scssCompiler->setFormatter($debug ? Expanded::class : Crunched::class);
    }

    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $withAssets = true
    ): void {
        $themePrefix = self::getThemePrefix($salesChannelId, $themeId);
        $outputPath = 'theme' . DIRECTORY_SEPARATOR . $themePrefix;

        if ($withAssets && is_dir('theme' . DIRECTORY_SEPARATOR . $themePrefix)) {
            $this->publicFilesystem->deleteDir($outputPath);
        }

        $resolvedFiles = $this->themeFileResolver->resolveFiles($themeConfig, $configurationCollection, false);
        /** @var FileCollection $styleFiles */
        $styleFiles = $resolvedFiles[ThemeFileResolver::STYLE_FILES];

        $concatenatedStyles = '';
        foreach ($styleFiles as $file) {
            $concatenatedStyles .= '@import \'' . $file->getFilepath() . '\';' . PHP_EOL;
        }
        $compiled = $this->compileStyles($concatenatedStyles, $themeConfig, $styleFiles->getResolveMappings());
        $cssFilepath = $outputPath . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'all.css';
        $this->publicFilesystem->put($cssFilepath, $compiled);

        /** @var FileCollection $scriptFiles */
        $scriptFiles = $resolvedFiles[ThemeFileResolver::SCRIPT_FILES];
        $concatenatedScripts = '';
        foreach ($scriptFiles->getFilepaths() as $scriptPath) {
            $concatenatedScripts .= file_get_contents($scriptPath) . PHP_EOL;
        }

        $scriptFilepath = $outputPath . DIRECTORY_SEPARATOR . 'js' . DIRECTORY_SEPARATOR . 'all.js';
        $this->publicFilesystem->put($scriptFilepath, $concatenatedScripts);

        // assets
        if ($withAssets) {
            $this->copyAssets($themeConfig, $configurationCollection, $outputPath);
        }
    }

    public static function getThemePrefix(string $salesChannelId, string $themeId): string
    {
        return md5($themeId . $salesChannelId);
    }

    private function copyAssets(
        StorefrontPluginConfiguration $configuration,
        StorefrontPluginConfigurationCollection $configurationCollection,
        string $outputPath
    ): void {
        if (!$configuration->getAssetPaths()) {
            return;
        }

        foreach ($configuration->getAssetPaths() as $asset) {
            if (mb_strpos($asset, '@') === 0) {
                $name = mb_substr($asset, 1);
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
                    'bundles' . DIRECTORY_SEPARATOR . mb_strtolower($configuration->getTechnicalName()) . DIRECTORY_SEPARATOR . $assetDir . DIRECTORY_SEPARATOR . $relativePathname,
                    $content
                );

                $this->publicFilesystem->put(
                    $outputPath . DIRECTORY_SEPARATOR . $assetDir . DIRECTORY_SEPARATOR . $relativePathname,
                    $content
                );
            }
        }
    }

    private function compileStyles(
        string $concatenatedStyles,
        StorefrontPluginConfiguration $configuration,
        array $resolveMappings
    ): string {
        $this->scssCompiler->addImportPath(function ($originalPath) use ($resolveMappings) {
            foreach ($resolveMappings as $resolve => $resolvePath) {
                $resolve = '~' . $resolve;
                if (mb_strpos($originalPath, $resolve) === 0) {
                    $dirname = $resolvePath . dirname(mb_substr($originalPath, mb_strlen($resolve)));
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

        $variables = $this->dumpVariables($configuration->getThemeConfig());
        $cssOutput = $this->scssCompiler->compile($variables . $concatenatedStyles);
        $autoPreFixer = new Autoprefixer($cssOutput);

        return $autoPreFixer->compile();
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

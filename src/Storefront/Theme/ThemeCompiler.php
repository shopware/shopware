<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemInterface;
use Padaliyajay\PHPAutoprefixer\Autoprefixer;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Formatter\Crunched;
use ScssPhp\ScssPhp\Formatter\Expanded;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Exception\InvalidThemeException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Finder;

class ThemeCompiler implements ThemeCompilerInterface
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

    /**
     * @var ThemeFileImporterInterface|null
     */
    private $themeFileImporter;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var FilesystemInterface
     */
    private $tempFilesystem;

    /**
     * @param ThemeFileImporterInterface|null $themeFileImporter will be required in v6.3.0
     */
    public function __construct(
        FilesystemInterface $publicFilesystem,
        FilesystemInterface $tempFilesystem,
        ThemeFileResolver $themeFileResolver,
        string $cacheDir,
        bool $debug,
        EventDispatcherInterface $eventDispatcher,
        ?ThemeFileImporterInterface $themeFileImporter = null
    ) {
        $this->publicFilesystem = $publicFilesystem;
        $this->tempFilesystem = $tempFilesystem;
        $this->themeFileResolver = $themeFileResolver;
        $this->cacheDir = $cacheDir;
        $this->themeFileImporter = $themeFileImporter;

        $this->scssCompiler = new Compiler();
        $this->scssCompiler->setImportPaths('');

        $this->scssCompiler->setFormatter($debug ? Expanded::class : Crunched::class);
        $this->eventDispatcher = $eventDispatcher;
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

        if ($withAssets && $this->publicFilesystem->has($outputPath)) {
            $this->publicFilesystem->deleteDir($outputPath);
        }

        $resolvedFiles = $this->themeFileResolver->resolveFiles($themeConfig, $configurationCollection, false);
        /** @var FileCollection $styleFiles */
        $styleFiles = $resolvedFiles[ThemeFileResolver::STYLE_FILES];

        $concatenatedStyles = '';
        foreach ($styleFiles as $file) {
            if ($this->themeFileImporter) {
                $concatenatedStyles .= $this->themeFileImporter->getConcatenableStylePath($file, $themeConfig);
            } else {
                $concatenatedStyles .= '@import \'' . $file->getFilepath() . '\';' . PHP_EOL;
            }
        }
        $compiled = $this->compileStyles($concatenatedStyles, $themeConfig, $styleFiles->getResolveMappings(), $salesChannelId);
        $cssFilepath = $outputPath . DIRECTORY_SEPARATOR . 'css' . DIRECTORY_SEPARATOR . 'all.css';
        $this->publicFilesystem->put($cssFilepath, $compiled);

        /** @var FileCollection $scriptFiles */
        $scriptFiles = $resolvedFiles[ThemeFileResolver::SCRIPT_FILES];
        $concatenatedScripts = '';
        foreach ($scriptFiles as $file) {
            if ($this->themeFileImporter) {
                $concatenatedScripts .= $this->themeFileImporter->getConcatenableScriptPath($file, $themeConfig);
            } else {
                $concatenatedScripts .= file_get_contents($file->getFilepath()) . PHP_EOL;
            }
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

            if ($this->themeFileImporter) {
                $assets = $this->themeFileImporter->getCopyBatchInputsForAssets($asset, $outputPath, $configuration);
            } else {
                $assets = $this->getCopyBatchInputsForAssets($configuration, $outputPath, $asset);
            }

            // method copyBatch is provided by copyBatch filesystem plugin
            $this->publicFilesystem->copyBatch(...$assets);
        }
    }

    private function compileStyles(
        string $concatenatedStyles,
        StorefrontPluginConfiguration $configuration,
        array $resolveMappings,
        string $salesChannelId
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

        $variables = $this->dumpVariables($configuration->getThemeConfig(), $salesChannelId);

        try {
            $cssOutput = $this->scssCompiler->compile($variables . $concatenatedStyles);
        } catch (\Throwable $exception) {
            throw new ThemeCompileException(
                $configuration->getTechnicalName(),
                $exception->getMessage()
            );
        }
        $autoPreFixer = new Autoprefixer($cssOutput);

        return $autoPreFixer->compile();
    }

    private function formatVariables(array $variables): array
    {
        return array_map(function ($value, $key) {
            return sprintf('$%s: %s;', $key, $value);
        }, $variables, array_keys($variables));
    }

    private function dumpVariables(array $config, string $salesChannelId): string
    {
        if (!array_key_exists('fields', $config)) {
            return '';
        }

        $variables = [];
        foreach ($config['fields'] as $key => $data) {
            if (array_key_exists('value', $data) && $data['value']) {
                // Do not include fields which have the scss option set to false
                if (array_key_exists('scss', $data) && $data['scss'] === false) {
                    continue;
                }

                if ($data['type'] === 'media') {
                    $variables[$key] = '\'' . $data['value'] . '\'';
                } else {
                    $variables[$key] = $data['value'];
                }
            }
        }

        $themeVariablesEvent = new ThemeCompilerEnrichScssVariablesEvent($variables, $salesChannelId);
        $this->eventDispatcher->dispatch($themeVariablesEvent);

        $dump = str_replace(
            ['#class#', '#variables#'],
            [self::class, implode(PHP_EOL, $this->formatVariables($themeVariablesEvent->getVariables()))],
            $this->getVariableDumpTemplate()
        );

        $this->tempFilesystem->put('theme-variables.scss', $dump);

        return $dump;
    }

    private function getVariableDumpTemplate(): string
    {
        return <<<PHP_EOL
// ATTENTION! This file is auto generated by the #class# and should not be edited.

#variables#

PHP_EOL;
    }

    /**
     * @deprecated tag:v6.3.0 can safely be removed once the themeFileImporter prop is required
     */
    private function getCopyBatchInputsForAssets(StorefrontPluginConfiguration $configuration, string $outputPath, $asset): array
    {
        if (!is_dir($asset)) {
            throw new ThemeCompileException(
                $configuration->getTechnicalName(),
                sprintf('Unable to find asset. Path: "%s"', $asset)
            );
        }

        $finder = new Finder();
        $files = $finder->files()->in($asset);
        $assets = [];

        foreach ($files as $file) {
            $relativePathname = $file->getRelativePathname();
            $assetDir = basename($asset);

            $assets[] = new CopyBatchInput(
                $asset . DIRECTORY_SEPARATOR . $relativePathname,
                [
                    'bundles' . DIRECTORY_SEPARATOR . mb_strtolower($configuration->getTechnicalName()) . DIRECTORY_SEPARATOR . $assetDir . DIRECTORY_SEPARATOR . $relativePathname,
                    $outputPath . DIRECTORY_SEPARATOR . $assetDir . DIRECTORY_SEPARATOR . $relativePathname,
                ]
            );
        }

        return $assets;
    }
}

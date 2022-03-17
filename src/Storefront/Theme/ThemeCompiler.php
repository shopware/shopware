<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemInterface;
use Padaliyajay\PHPAutoprefixer\Autoprefixer;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Feature;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedScriptsEvent;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Event\ThemeCopyToLiveEvent;
use Shopware\Storefront\Theme\Exception\InvalidThemeException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\Exception\ThemeFileCopyException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Component\Asset\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ThemeCompiler implements ThemeCompilerInterface
{
    private FilesystemInterface $filesystem;

    private Compiler $scssCompiler;

    private ThemeFileResolver $themeFileResolver;

    private ThemeFileImporterInterface $themeFileImporter;

    private EventDispatcherInterface $eventDispatcher;

    private FilesystemInterface $tempFilesystem;

    /**
     * @var Package[]
     */
    private array $packages;

    private CacheInvalidator $logger;

    private AbstractThemePathBuilder $themePathBuilder;

    private bool $debug;

    private string $projectDir;

    public function __construct(
        FilesystemInterface $filesystem,
        FilesystemInterface $tempFilesystem,
        ThemeFileResolver $themeFileResolver,
        bool $debug,
        EventDispatcherInterface $eventDispatcher,
        ThemeFileImporterInterface $themeFileImporter,
        iterable $packages,
        CacheInvalidator $logger,
        AbstractThemePathBuilder $themePathBuilder,
        string $projectDir
    ) {
        $this->filesystem = $filesystem;
        $this->tempFilesystem = $tempFilesystem;
        $this->themeFileResolver = $themeFileResolver;
        $this->themeFileImporter = $themeFileImporter;

        $this->scssCompiler = new Compiler();
        $cwd = \getcwd();

        $this->scssCompiler->setImportPaths($cwd === false ? '' : $cwd);

        $this->scssCompiler->setOutputStyle($debug ? OutputStyle::EXPANDED : OutputStyle::COMPRESSED);
        $this->eventDispatcher = $eventDispatcher;
        $this->packages = $packages;
        $this->logger = $logger;
        $this->themePathBuilder = $themePathBuilder;
        $this->debug = $debug;
        $this->projectDir = $projectDir;
    }

    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $withAssets = true
    ): void {
        /**
         * @feature-deprecated (flag:FEATURE_NEXT_15381) keep if branch remove complete following on feature release
         */
        if (Feature::isActive('FEATURE_NEXT_15381')) {
            $themePrefix = $this->themePathBuilder->assemblePath($salesChannelId, $themeId);
            $tmpOutputPath = 'theme' . \DIRECTORY_SEPARATOR . 'temp' . \DIRECTORY_SEPARATOR . $themePrefix;

            if ($withAssets && $this->filesystem->has($tmpOutputPath)) {
                $this->filesystem->deleteDir($tmpOutputPath);
            }

            $resolvedFiles = $this->themeFileResolver->resolveFiles($themeConfig, $configurationCollection, false);
            /** @var FileCollection $styleFiles */
            $styleFiles = $resolvedFiles[ThemeFileResolver::STYLE_FILES];

            $concatenatedStyles = '';
            foreach ($styleFiles as $file) {
                $concatenatedStyles .= $this->themeFileImporter->getConcatenableStylePath($file, $themeConfig);
            }
            $concatenatedStylesEvent = new ThemeCompilerConcatenatedStylesEvent($concatenatedStyles, $salesChannelId);
            $this->eventDispatcher->dispatch($concatenatedStylesEvent);
            $compiled = $this->compileStyles($concatenatedStylesEvent->getConcatenatedStyles(), $themeConfig, $styleFiles->getResolveMappings(), $salesChannelId);
            $tmpCssFilepath = $tmpOutputPath . \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'all.css';
            $this->filesystem->put($tmpCssFilepath, $compiled);

            /** @var FileCollection $scriptFiles */
            $scriptFiles = $resolvedFiles[ThemeFileResolver::SCRIPT_FILES];
            $concatenatedScripts = '';
            foreach ($scriptFiles as $file) {
                $concatenatedScripts .= $this->themeFileImporter->getConcatenableScriptPath($file, $themeConfig);
            }
            $concatenatedScriptsEvent = new ThemeCompilerConcatenatedScriptsEvent($concatenatedScripts, $salesChannelId);
            $this->eventDispatcher->dispatch($concatenatedScriptsEvent);

            $tmpScriptFilepath = $tmpOutputPath . \DIRECTORY_SEPARATOR . 'js' . \DIRECTORY_SEPARATOR . 'all.js';
            $this->filesystem->put($tmpScriptFilepath, $concatenatedScriptsEvent->getConcatenatedScripts());

            // assets
            if ($withAssets) {
                $this->copyAssets($themeConfig, $configurationCollection, $tmpOutputPath);
            }

            $backupOutputPath = 'theme' . \DIRECTORY_SEPARATOR . 'backup' . \DIRECTORY_SEPARATOR . $themePrefix;
            $outputPath = 'theme' . \DIRECTORY_SEPARATOR . $themePrefix;
            $this->copyToLiveLocation($outputPath, $backupOutputPath, $tmpOutputPath, $themeId);
            // Reset cache buster state for improving performance in getMetadata
            $this->logger->invalidate(['theme-metaData'], true);
            /**
             * @feature-deprecated (flag:FEATURE_NEXT_15381) remove return statement on feature release
             */
            return;
        }

        $themePrefix = $this->themePathBuilder->assemblePath($salesChannelId, $themeId);
        $outputPath = 'theme' . \DIRECTORY_SEPARATOR . $themePrefix;

        if ($withAssets && $this->filesystem->has($outputPath)) {
            $this->filesystem->deleteDir($outputPath);
        }

        $resolvedFiles = $this->themeFileResolver->resolveFiles($themeConfig, $configurationCollection, false);
        /** @var FileCollection $styleFiles */
        $styleFiles = $resolvedFiles[ThemeFileResolver::STYLE_FILES];

        $concatenatedStyles = '';
        foreach ($styleFiles as $file) {
            $concatenatedStyles .= $this->themeFileImporter->getConcatenableStylePath($file, $themeConfig);
        }
        $concatenatedStylesEvent = new ThemeCompilerConcatenatedStylesEvent($concatenatedStyles, $salesChannelId);
        $this->eventDispatcher->dispatch($concatenatedStylesEvent);
        $compiled = $this->compileStyles($concatenatedStylesEvent->getConcatenatedStyles(), $themeConfig, $styleFiles->getResolveMappings(), $salesChannelId);
        $cssFilepath = $outputPath . \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'all.css';
        $this->filesystem->put($cssFilepath, $compiled);

        /** @var FileCollection $scriptFiles */
        $scriptFiles = $resolvedFiles[ThemeFileResolver::SCRIPT_FILES];
        $concatenatedScripts = '';
        foreach ($scriptFiles as $file) {
            $concatenatedScripts .= $this->themeFileImporter->getConcatenableScriptPath($file, $themeConfig);
        }
        $concatenatedScriptsEvent = new ThemeCompilerConcatenatedScriptsEvent($concatenatedScripts, $salesChannelId);
        $this->eventDispatcher->dispatch($concatenatedScriptsEvent);

        $scriptFilepath = $outputPath . \DIRECTORY_SEPARATOR . 'js' . \DIRECTORY_SEPARATOR . 'all.js';
        $this->filesystem->put($scriptFilepath, $concatenatedScriptsEvent->getConcatenatedScripts());

        // assets
        if ($withAssets) {
            $this->copyAssets($themeConfig, $configurationCollection, $outputPath);
        }

        // Reset cache buster state for improving performance in getMetadata
        $this->logger->invalidate(['theme-metaData'], true);
    }

    /**
     * @deprecated tag:v6.5.0 - Use AbstractThemePathBuilder instead
     */
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

            if ($asset[0] !== '/' && file_exists($this->projectDir . '/' . $asset)) {
                $asset = $this->projectDir . '/' . $asset;
            }

            $assets = $this->themeFileImporter->getCopyBatchInputsForAssets($asset, $outputPath, $configuration);

            // method copyBatch is provided by copyBatch filesystem plugin
            $this->filesystem->copyBatch(...$assets);
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
                    /**
                     * @deprecated tag:v6.5.0 - Alias `vendorBootstrap` will be removed.
                     *
                     * Alias is used to import Bootstrap v5 instead of Bootstrap v4 if feature flag v6.5.0.0 is active.
                     * Package `bootstrap5` will be renamed to `bootstrap` and replace Bootstrap v4.
                     * Remove this if completely.
                     */
                    if (mb_strpos($originalPath, '~vendorBootstrap/') === 0) {
                        $originalPath = Feature::isActive('v6.5.0.0')
                            ? str_replace('~vendorBootstrap/', '~vendor/bootstrap5/', $originalPath)
                            : str_replace('~vendorBootstrap/', '~vendor/bootstrap/', $originalPath);
                    }

                    $dirname = $resolvePath . \dirname(mb_substr($originalPath, mb_strlen($resolve)));

                    $filename = basename($originalPath);
                    $extension = pathinfo($filename, \PATHINFO_EXTENSION) === '' ? '.scss' : '';
                    $path = $dirname . \DIRECTORY_SEPARATOR . $filename . $extension;
                    if (file_exists($path)) {
                        return $path;
                    }

                    $path = $dirname . \DIRECTORY_SEPARATOR . '_' . $filename . $extension;
                    if (file_exists($path)) {
                        return $path;
                    }
                }
            }

            return null;
        });

        $variables = $this->dumpVariables($configuration->getThemeConfig(), $salesChannelId);
        $features = $this->getFeatureConfigScssMap();

        try {
            $cssOutput = $this->scssCompiler->compileString($features . $variables . $concatenatedStyles)->getCss();
        } catch (\Throwable $exception) {
            throw new ThemeCompileException(
                $configuration->getTechnicalName(),
                $exception->getMessage()
            );
        }
        $autoPreFixer = new Autoprefixer($cssOutput);
        /** @var string|false $compiled */
        $compiled = $autoPreFixer->compile($this->debug);
        if ($compiled === false) {
            throw new ThemeCompileException(
                $configuration->getTechnicalName(),
                'CSS parser not initialized'
            );
        }

        return $compiled;
    }

    /**
     * Converts the feature config array to a SCSS map syntax.
     * This allows reading of the feature flag config inside SCSS via `map.get` function.
     *
     * Output example:
     * $sw-features: ("FEATURE_NEXT_1234": false, "FEATURE_NEXT_1235": true);
     *
     * @see https://sass-lang.com/documentation/values/maps
     */
    private function getFeatureConfigScssMap(): string
    {
        $allFeatures = Feature::getAll();

        $featuresScss = implode(',', array_map(function ($value, $key) {
            return sprintf('"%s": %s', $key, json_encode($value));
        }, $allFeatures, array_keys($allFeatures)));

        return sprintf('$sw-features: (%s);', $featuresScss);
    }

    private function formatVariables(array $variables): array
    {
        return array_map(function ($value, $key) {
            return sprintf('$%s: %s;', $key, $value);
        }, $variables, array_keys($variables));
    }

    private function copyToLiveLocation(string $path, string $backupPath, string $tmpPath, string $themeId): void
    {
        $themeCopyToLiveEvent = new ThemeCopyToLiveEvent($themeId, $path, $backupPath, $tmpPath);
        $this->eventDispatcher->dispatch($themeCopyToLiveEvent);

        $path = $themeCopyToLiveEvent->getPath();
        $backupPath = $themeCopyToLiveEvent->getBackupPath();
        $tmpPath = $themeCopyToLiveEvent->getTmpPath();

        if (
            !$this->filesystem->has($tmpPath)
            || ($this->filesystem->getMetaData($tmpPath) ?: ['type' => false])['type'] !== 'dir') {
            throw new ThemeFileCopyException(
                $themeId,
                sprintf('Compilation error. Compiled files not found in %s.', $tmpPath)
            );
        }

        // backup current theme files
        if ($this->filesystem->has($path)) {
            try {
                $this->filesystem->deleteDir($backupPath);
                $this->filesystem->rename($path, $backupPath);
                echo $backupPath;
            } catch (\Throwable $e) {
                throw new ThemeFileCopyException($themeId, $e->getMessage());
            }
        }

        // move new theme files to live dir. Move backup back if something failed.
        try {
            $this->filesystem->rename($tmpPath, $path);
        } catch (\Throwable $e) {
            if ($this->filesystem->has($path)) {
                try {
                    $this->filesystem->rename($path, $backupPath);
                } catch (\Throwable $innerE) {
                    throw new ThemeFileCopyException($themeId, $innerE->getMessage());
                }
            }

            throw new ThemeFileCopyException($themeId, $e->getMessage());
        }
    }

    private function dumpVariables(array $config, string $salesChannelId): string
    {
        if (!\array_key_exists('fields', $config)) {
            return '';
        }

        $variables = [];
        foreach ($config['fields'] as $key => $data) {
            if (!\is_array($data) || !$this->isDumpable($data)) {
                continue;
            }

            if (\in_array($data['type'], ['media', 'textarea'], true)) {
                $variables[$key] = '\'' . $data['value'] . '\'';
            } elseif ($data['type'] === 'switch' || $data['type'] === 'checkbox') {
                $variables[$key] = (int) ($data['value']);
            } else {
                $variables[$key] = $data['value'];
            }
        }

        foreach ($this->packages as $key => $package) {
            $variables[sprintf('sw-asset-%s-url', $key)] = sprintf('\'%s\'', $package->getUrl(''));
        }

        $themeVariablesEvent = new ThemeCompilerEnrichScssVariablesEvent($variables, $salesChannelId);
        $this->eventDispatcher->dispatch($themeVariablesEvent);

        $dump = str_replace(
            ['#class#', '#variables#'],
            [self::class, implode(\PHP_EOL, $this->formatVariables($themeVariablesEvent->getVariables()))],
            $this->getVariableDumpTemplate()
        );

        $this->tempFilesystem->put('theme-variables.scss', $dump);

        return $dump;
    }

    private function isDumpable(array $data): bool
    {
        if (!isset($data['value'])) {
            return false;
        }

        // Do not include fields which have the scss option set to false
        if (\array_key_exists('scss', $data) && $data['scss'] === false) {
            return false;
        }

        // Do not include fields which haa an array as value
        if (\is_array($data['value'])) {
            return false;
        }

        // value must not be an empty string since because an empty value can not be compiled
        if ($data['value'] === '') {
            return false;
        }

        // if no type is set just use the value and continue
        if (!isset($data['type'])) {
            return false;
        }

        return true;
    }

    private function getVariableDumpTemplate(): string
    {
        return <<<PHP_EOL
// ATTENTION! This file is auto generated by the #class# and should not be edited.

#variables#

PHP_EOL;
    }
}

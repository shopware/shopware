<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemInterface;
use Padaliyajay\PHPAutoprefixer\Autoprefixer;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedScriptsEvent;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent as ThemeCompilerEnrichScssVariablesEventNew;
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

    private AbstractScssCompiler $scssCompiler;

    private ThemeFileResolver $themeFileResolver;

    private ThemeFileImporterInterface $themeFileImporter;

    private EventDispatcherInterface $eventDispatcher;

    private FilesystemInterface $tempFilesystem;

    /**
     * @var Package[]
     */
    private iterable $packages;

    private CacheInvalidator $logger;

    private AbstractThemePathBuilder $themePathBuilder;

    private bool $debug;

    private string $projectDir;

    /**
     * @internal
     *
     * @param Package[] $packages
     */
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
        string $projectDir,
        AbstractScssCompiler $scssCompiler
    ) {
        $this->filesystem = $filesystem;
        $this->tempFilesystem = $tempFilesystem;
        $this->themeFileResolver = $themeFileResolver;
        $this->themeFileImporter = $themeFileImporter;

        $this->scssCompiler = $scssCompiler;

        $this->eventDispatcher = $eventDispatcher;
        $this->packages = $packages;
        $this->logger = $logger;
        $this->themePathBuilder = $themePathBuilder;
        $this->debug = $debug;
        $this->projectDir = $projectDir;
    }

    /**
     * @param Context $context - @deprecated tag:v6.5.0 parameter $context will be required in v6.5.0.0
     */
    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $withAssets = true/*,
        Context $context = null */
    ): void {
        if (\func_num_args() === 6) {
            $context = func_get_arg(5);
        } else {
            $context = null;
            Feature::triggerDeprecationOrThrow(
                'v6.5.0.0',
                sprintf('The parameter context in method compileTheme of class %s is mandatory.', static::class)
            );
        }

        /**
         * @feature-deprecated (flag:FEATURE_NEXT_15381) keep if branch remove complete following on feature release
         */
        if (Feature::isActive('FEATURE_NEXT_15381')) {
            $themePrefix = $this->themePathBuilder->assemblePath($salesChannelId, $themeId);

            $resolvedFiles = $this->themeFileResolver->resolveFiles($themeConfig, $configurationCollection, false);

            $styleFiles = $resolvedFiles[ThemeFileResolver::STYLE_FILES];

            $concatenatedStyles = $this->concatenateStyles(
                $styleFiles,
                $themeConfig,
                $salesChannelId
            );

            $compiled = $this->compileStyles(
                $concatenatedStyles,
                $themeConfig,
                $styleFiles->getResolveMappings(),
                $salesChannelId,
                $context
            );

            if ($this->filesystem->has($this->getTmpOutputPath($themePrefix))) {
                $this->filesystem->deleteDir($this->getTmpOutputPath($themePrefix));
            }

            if ($this->scssCompiler->filesHandledInternal() === false) {
                $this->filesystem->put($this->getTmpCssFilepath($themePrefix), $compiled);
            }

            $concatenatedScripts = $this->getConcatenatedScripts($resolvedFiles[ThemeFileResolver::SCRIPT_FILES], $themeConfig, $salesChannelId);

            $this->writeScriptFiles($this->getTmpOutputPath($themePrefix), $concatenatedScripts);

            // assets
            if ($withAssets) {
                $this->copyAssets($themeConfig, $configurationCollection, $this->getTmpOutputPath($themePrefix));
            }

            $this->copyToLiveLocation($themePrefix, $themeId);

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
        $compiled = $this->compileStyles(
            $concatenatedStylesEvent->getConcatenatedStyles(),
            $themeConfig,
            $styleFiles->getResolveMappings(),
            $salesChannelId,
            $context
        );
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
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(__CLASS__, __METHOD__, 'v6.5.0.0', 'AbstractThemePathBuilder')
        );

        return md5($themeId . $salesChannelId);
    }

    /**
     * @param array<string, string> $resolveMappings
     */
    public function getResolveImportPathsCallback(array $resolveMappings): \Closure
    {
        return function ($originalPath) use ($resolveMappings) {
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
                    $extension = $this->getImportFileExtension(pathinfo($filename, \PATHINFO_EXTENSION));
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
        };
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

    /**
     * @deprecated tag:v6.5.0 - $context will be mandatory
     *
     * @param array<string, string> $resolveMappings
     */
    private function compileStyles(
        string $concatenatedStyles,
        StorefrontPluginConfiguration $configuration,
        array $resolveMappings,
        string $salesChannelId,
        ?Context $context = null
    ): string {
        if ($context === null) {
            $context = Context::createDefaultContext();
        }

        $variables = $this->dumpVariables($configuration->getThemeConfig() ?? [], $salesChannelId, $context);
        $features = $this->getFeatureConfigScssMap();

        $resolveImportPath = $this->getResolveImportPathsCallback($resolveMappings);

        $importPaths = [];

        $cwd = \getcwd();
        if ($cwd !== false) {
            $importPaths[] = $cwd;
        }

        $importPaths[] = $resolveImportPath;

        $compilerConfig = new CompilerConfiguration(
            [
                'importPaths' => $importPaths,
                'outputStyle' => $this->debug ? OutputStyle::EXPANDED : OutputStyle::COMPRESSED,
            ]
        );

        try {
            $cssOutput = $this->scssCompiler->compileString(
                $compilerConfig,
                $features . $variables . $concatenatedStyles
            );
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

    private function getImportFileExtension(string $extension): string
    {
        // If the import has no extension, it must be a SCSS module.
        if ($extension === '') {
            return '.scss';
        }

        // If the import has a .min extension, we assume it must be a compiled CSS file.
        if ($extension === 'min') {
            return '.css';
        }

        // If it has any other extension, we don't assume a specific extension.
        return '';
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

    /**
     * @param array<string, string> $variables
     *
     * @return array<string>
     */
    private function formatVariables(array $variables): array
    {
        return array_map(function ($value, $key) {
            return sprintf('$%s: %s;', $key, (!empty($value) ? $value : 0));
        }, $variables, array_keys($variables));
    }

    private function copyToLiveLocation(string $themePrefix, string $themeId): void
    {
        $backupPath = 'theme' . \DIRECTORY_SEPARATOR . 'backup' . \DIRECTORY_SEPARATOR . $themePrefix;
        $path = 'theme' . \DIRECTORY_SEPARATOR . $themePrefix;

        $themeCopyToLiveEvent = new ThemeCopyToLiveEvent($themeId, $path, $backupPath, $this->getTmpOutputPath($themePrefix));
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

    /**
     * @param array{fields?: array{value: null|string|array<mixed>, scss?: bool, type: string}[]} $config
     */
    private function dumpVariables(array $config, string $salesChannelId, Context $context): string
    {
        $variables = [];
        foreach ($config['fields'] ?? [] as $key => $data) {
            if (!\is_array($data) || !$this->isDumpable($data)) {
                continue;
            }

            if (\in_array($data['type'], ['media', 'textarea'], true) && \is_string($data['value'])) {
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

        Feature::callSilentIfInactive('v6.5.0.0', function () use (&$variables, $salesChannelId): void {
            /** @deprecated tag:v6.5.0 remove this event in 6.5.0 */
            $themeVariablesEvent = new ThemeCompilerEnrichScssVariablesEvent($variables, $salesChannelId);
            $this->eventDispatcher->dispatch($themeVariablesEvent);
            $variables = $themeVariablesEvent->getVariables();
        });

        /** @deprecated tag:v6.5.0 remove alias */
        $themeVariablesEvent = new ThemeCompilerEnrichScssVariablesEventNew(
            $variables,
            $salesChannelId,
            $context
        );

        $this->eventDispatcher->dispatch($themeVariablesEvent);

        $dump = str_replace(
            ['#class#', '#variables#'],
            [self::class, implode(\PHP_EOL, $this->formatVariables($themeVariablesEvent->getVariables()))],
            $this->getVariableDumpTemplate()
        );

        $this->tempFilesystem->put('theme-variables.scss', $dump);

        return $dump;
    }

    /**
     * @param array{value: string|array<mixed>|null, scss?: bool, type: string} $data
     */
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

    private function writeScriptFiles(
        string $tmpOutputPath,
        string $concatenatedScripts
    ): void {
        $tmpScriptFilepath = $tmpOutputPath . \DIRECTORY_SEPARATOR . 'js' . \DIRECTORY_SEPARATOR . 'all.js';
        $this->filesystem->put($tmpScriptFilepath, $concatenatedScripts);
    }

    private function getTmpOutputPath(string $themePrefix): string
    {
        return 'theme' . \DIRECTORY_SEPARATOR . 'temp' . \DIRECTORY_SEPARATOR . $themePrefix;
    }

    private function getTmpCssFilepath(string $themePrefix): string
    {
        return $this->getTmpOutputPath($themePrefix) . \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'all.css';
    }

    private function concatenateStyles(
        FileCollection $styleFiles,
        StorefrontPluginConfiguration $themeConfig,
        string $salesChannelId
    ): string {
        $concatenatedStyles = '';
        foreach ($styleFiles as $file) {
            $concatenatedStyles .= $this->themeFileImporter->getConcatenableStylePath($file, $themeConfig);
        }
        $concatenatedStylesEvent = new ThemeCompilerConcatenatedStylesEvent($concatenatedStyles, $salesChannelId);
        $this->eventDispatcher->dispatch($concatenatedStylesEvent);

        return $concatenatedStylesEvent->getConcatenatedStyles();
    }

    private function getConcatenatedScripts(
        FileCollection $scriptFiles,
        StorefrontPluginConfiguration $themeConfig,
        string $salesChannelId
    ): string {
        $concatenatedScripts = '';
        foreach ($scriptFiles as $file) {
            $concatenatedScripts .= $this->themeFileImporter->getConcatenableScriptPath($file, $themeConfig);
        }

        $concatenatedScriptsEvent = new ThemeCompilerConcatenatedScriptsEvent($concatenatedScripts, $salesChannelId);
        $this->eventDispatcher->dispatch($concatenatedScriptsEvent);

        return $concatenatedScriptsEvent->getConcatenatedScripts();
    }
}

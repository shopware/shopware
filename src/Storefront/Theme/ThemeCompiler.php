<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteDirectory;
use Padaliyajay\PHPAutoprefixer\Autoprefixer;
use Psr\Log\LoggerInterface;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatch;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\Message\DeleteThemeFilesMessage;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Component\Asset\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

#[\Shopware\Core\Framework\Log\Package('storefront')]
class ThemeCompiler implements ThemeCompilerInterface
{
    /**
     * @internal
     *
     * @param array<string, Package> $packages
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly FilesystemOperator $tempFilesystem,
        private readonly CopyBatchInputFactory $copyBatchInputFactory,
        private readonly ThemeFileResolver $themeFileResolver,
        private readonly bool $debug,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ThemeFilesystemResolver $themeFilesystemResolver,
        private readonly iterable $packages,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly LoggerInterface $logger,
        private readonly AbstractThemePathBuilder $themePathBuilder,
        private readonly string $projectDir,
        private readonly AbstractScssCompiler $scssCompiler,
        private readonly MessageBusInterface $messageBus,
        private readonly int $themeFileDeleteDelay,
        private readonly bool $autoPrefix = false
    ) {
    }

    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $withAssets,
        Context $context
    ): void {
        try {
            $resolvedFiles = $this->themeFileResolver->resolveFiles($themeConfig, $configurationCollection, false);

            $styleFiles = $resolvedFiles[ThemeFileResolver::STYLE_FILES];
        } catch (\Throwable $e) {
            throw new ThemeCompileException(
                $themeConfig->getName() ?? '',
                'Files could not be resolved with error: ' . $e->getMessage(),
                $e
            );
        }

        try {
            $concatenatedStyles = $this->concatenateStyles($styleFiles, $salesChannelId);
        } catch (\Throwable $e) {
            throw new ThemeCompileException(
                $themeConfig->getName() ?? '',
                'Error while trying to concatenate Styles: ' . $e->getMessage(),
                $e
            );
        }

        $compiled = $this->compileStyles(
            $concatenatedStyles,
            $themeConfig,
            $styleFiles->getResolveMappings(),
            $salesChannelId,
            $themeId,
            $context
        );

        $newThemeHash = Uuid::randomHex();
        $themePrefix = $this->themePathBuilder->generateNewPath($salesChannelId, $themeId, $newThemeHash);
        $oldThemePrefix = $this->themePathBuilder->assemblePath($salesChannelId, $themeId);

        // If the system does not use seeded theme paths,
        // we have to delete the complete folder before to ensure that old files are deleted
        if ($oldThemePrefix === $themePrefix) {
            $path = 'theme' . \DIRECTORY_SEPARATOR . $themePrefix;

            $this->filesystem->deleteDirectory($path);
        }

        try {
            $assets = $this->collectCompiledFiles($themePrefix, $themeId, $compiled, $withAssets, $themeConfig, $configurationCollection);
        } catch (\Throwable $e) {
            throw new ThemeCompileException(
                $themeConfig->getName() ?? '',
                'Error while trying to write compiled files: ' . $e->getMessage(),
                $e
            );
        }

        $scriptFiles = $this->copyScriptFilesToTheme($configurationCollection, $themePrefix);

        CopyBatch::copy($this->filesystem, ...$assets, ...$scriptFiles);

        $this->themePathBuilder->saveSeed($salesChannelId, $themeId, $newThemeHash);

        // only delete the old directory if the `themePathBuilder` actually returned a new path and supports seeding
        if ($themePrefix !== $oldThemePrefix) {
            $stamps = [];

            if ($this->themeFileDeleteDelay > 0) {
                // also delete with a delay, so that the old theme is still available for a while in case some CDN delivers stale content
                // delay is configured in seconds, symfony expects milliseconds
                $stamps[] = new DelayStamp($this->themeFileDeleteDelay * 1000);
            }

            $this->messageBus->dispatch(
                new DeleteThemeFilesMessage($oldThemePrefix, $salesChannelId, $themeId),
                $stamps
            );
        }

        // Reset cache buster state for improving performance in getMetadata
        $this->cacheInvalidator->invalidate([
            'theme-metaData',
            'theme_scripts_' . $themePrefix,
        ], true);
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

    /**
     * @return list<CopyBatchInput>
     */
    private function copyScriptFilesToTheme(
        StorefrontPluginConfigurationCollection $configurationCollection,
        string $themePrefix
    ): array {
        $scriptsDist = $this->getScriptDistFolders($configurationCollection);
        $themePath = 'theme/' . $themePrefix;
        $distRelativePath = 'Resources/app/storefront/dist/storefront';

        $copyFiles = [];

        foreach ($scriptsDist as $folderName => [$pluginConfig, $basePath]) {
            // For themes, we get basePath with Resources and for Plugins without, so we always remove and add it again
            $path = str_replace('/Resources', '', $basePath);
            $pathToJsFiles = $path . '/' . $distRelativePath . '/js/' . $folderName;
            if ($folderName === 'storefront') {
                $pathToJsFiles = $path . '/' . $distRelativePath;
            }

            if ($pathToJsFiles[0] === '/' || !file_exists($this->projectDir . '/' . $pathToJsFiles)) {
                $files = $this->getScriptDistFiles($pathToJsFiles);
            } else {
                $fs = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($pluginConfig);
                $path = $this->themeFilesystemResolver->makePathRelativeToFilesystemRoot($pathToJsFiles, $pluginConfig);

                $files = $this->getScriptDistFiles($fs->realpath($path));
            }

            if ($files === null) {
                continue;
            }

            $targetPath = $themePath . '/js/' . $folderName;
            foreach ($files as $file) {
                if (file_exists($file->getRealPath())) {
                    $copyFiles[] = new CopyBatchInput($file->getRealPath(), [$targetPath . '/' . $file->getFilename()]);
                }
            }
        }

        return $copyFiles;
    }

    /**
     * @return array<string, array{0: StorefrontPluginConfiguration, 1: string}>
     */
    private function getScriptDistFolders(StorefrontPluginConfigurationCollection $configurationCollection): array
    {
        $scriptsDistFolders = [];
        foreach ($configurationCollection as $configuration) {
            $scripts = $configuration->getScriptFiles();
            foreach ($scripts as $key => $script) {
                if ($script->getFilepath() === '@Storefront') {
                    $scripts->remove($key);
                }
            }
            if ($scripts->count() === 0) {
                continue;
            }
            $distPath = $configuration->getBasePath();
            $isVendor = str_contains($configuration->getBasePath(), 'vendor/');
            $isTechnicalName = str_contains($configuration->getBasePath(), $configuration->getTechnicalName());
            if (!$isVendor && !$isTechnicalName) {
                $appPath = '/' . $configuration->getTechnicalName() . '/Resources';
                $distPath = str_replace('/Resources', $appPath, $configuration->getBasePath());
            }

            $scriptsDistFolders[$configuration->getAssetName()] = [$configuration, $distPath];
        }

        return $scriptsDistFolders;
    }

    private function getScriptDistFiles(string $path): ?Finder
    {
        try {
            $finder = (new Finder())->files()->followLinks()->in($path)->exclude('js');
        } catch (DirectoryNotFoundException $e) {
            $this->logger->error($e->getMessage());
        }

        return $finder ?? null;
    }

    /**
     * @return list<CopyBatchInput>
     */
    private function getAssets(
        StorefrontPluginConfiguration $configuration,
        StorefrontPluginConfigurationCollection $configurationCollection,
        string $outputPath
    ): array {
        $collected = [];

        if (!$configuration->getAssetPaths()) {
            return [];
        }

        foreach ($configuration->getAssetPaths() as $asset) {
            if (mb_strpos((string) $asset, '@') === 0) {
                $name = mb_substr((string) $asset, 1);
                $config = $configurationCollection->getByTechnicalName($name);
                if (!$config) {
                    throw ThemeException::couldNotFindThemeByName($name);
                }

                $collected = [...$collected, ...$this->getAssets($config, $configurationCollection, $outputPath)];

                continue;
            }

            if ($asset[0] !== '/' && file_exists($this->projectDir . '/' . $asset)) {
                $asset = $this->projectDir . '/' . $asset;
            }

            $collected = [...$collected, ...$this->copyBatchInputFactory->fromDirectory($asset, $outputPath)];
        }

        return array_values($collected);
    }

    /**
     * @param array<string, string> $resolveMappings
     */
    private function compileStyles(
        string $concatenatedStyles,
        StorefrontPluginConfiguration $configuration,
        array $resolveMappings,
        string $salesChannelId,
        string $themeId,
        Context $context
    ): string {
        try {
            $variables = $this->dumpVariables($configuration->getThemeConfig() ?? [], $themeId, $salesChannelId, $context);
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

            $cssOutput = $this->scssCompiler->compileString(
                $compilerConfig,
                $features . $variables . $concatenatedStyles
            );
        } catch (\Throwable $exception) {
            throw new ThemeCompileException(
                $configuration->getTechnicalName(),
                $exception->getMessage(),
                $exception
            );
        }

        if ($this->autoPrefix === true) {
            $autoPreFixer = new Autoprefixer($cssOutput);
            /** @var string|false $cssOutput */
            $cssOutput = $autoPreFixer->compile($this->debug);
            if ($cssOutput === false) {
                throw new ThemeCompileException(
                    $configuration->getTechnicalName(),
                    'CSS parser not initialized'
                );
            }
        }

        return $cssOutput;
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

        $featuresScss = implode(',', array_map(fn ($value, $key) => sprintf('"%s": %s', $key, json_encode($value, \JSON_THROW_ON_ERROR)), $allFeatures, array_keys($allFeatures)));

        return sprintf('$sw-features: (%s);', $featuresScss);
    }

    /**
     * @param array<string, string|int> $variables
     *
     * @return array<string>
     */
    private function formatVariables(array $variables): array
    {
        return array_map(fn ($value, $key) => sprintf('$%s: %s;', $key, !empty($value) ? $value : 0), $variables, array_keys($variables));
    }

    /**
     * @param array{fields?: array{value: string|array<mixed>|null, scss?: bool, type: string}[]} $config
     */
    private function dumpVariables(array $config, string $themeId, string $salesChannelId, Context $context): string
    {
        $variables = [
            'theme-id' => $themeId,
        ];

        foreach ($config['fields'] ?? [] as $key => $data) {
            if (!\is_array($data) || !$this->isDumpable($data)) {
                continue;
            }

            if (\in_array($data['type'], ['media', 'textarea'], true) && \is_string($data['value'])) {
                $variables[$key] = '\'' . $data['value'] . '\'';
            } elseif ($data['type'] === 'switch' || $data['type'] === 'checkbox') {
                $variables[$key] = (int) $data['value'];
            } elseif (!\is_array($data['value'])) {
                $variables[$key] = (string) $data['value'];
            }
        }

        foreach ($this->packages as $key => $package) {
            $variables[sprintf('sw-asset-%s-url', $key)] = sprintf('\'%s\'', $package->getUrl(''));
        }

        $themeVariablesEvent = new ThemeCompilerEnrichScssVariablesEvent(
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

        $this->tempFilesystem->write('theme-variables.scss', $dump);

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

    private function concatenateStyles(
        FileCollection $styleFiles,
        string $salesChannelId
    ): string {
        $styles = $styleFiles->map(fn (File $file) => sprintf('@import \'%s\';', $file->getFilepath()));

        $concatenatedStylesEvent = new ThemeCompilerConcatenatedStylesEvent(
            implode("\n", $styles),
            $salesChannelId
        );
        $this->eventDispatcher->dispatch($concatenatedStylesEvent);

        return $concatenatedStylesEvent->getConcatenatedStyles();
    }

    /**
     * @return list<CopyBatchInput>
     */
    private function collectCompiledFiles(
        string $themePrefix,
        string $themeId,
        string $compiled,
        bool $withAssets,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection
    ): array {
        $compileLocation = 'theme' . \DIRECTORY_SEPARATOR . $themePrefix;

        $tempStream = fopen('php://temp', 'rwb');

        \assert(\is_resource($tempStream));
        fwrite($tempStream, $compiled);
        rewind($tempStream);

        $files = [
            new CopyBatchInput(
                $tempStream,
                [
                    $compileLocation . \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . 'all.css',
                ]
            ),
        ];

        // assets
        if ($withAssets) {
            $assetPath = 'theme' . \DIRECTORY_SEPARATOR . $themeId;

            try {
                $this->filesystem->deleteDirectory($assetPath);
            } catch (UnableToDeleteDirectory) {
            }

            $files = [...$files, ...$this->getAssets($themeConfig, $configurationCollection, $assetPath)];
        }

        return $files;
    }
}

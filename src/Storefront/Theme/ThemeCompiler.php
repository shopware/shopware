<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemException;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\Visibility;
use Psr\Log\LoggerInterface;
use ScssPhp\ScssPhp\OutputStyle;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatch;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInput;
use Shopware\Core\Framework\Adapter\Filesystem\Plugin\CopyBatchInputFactory;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Framework\Twig\Components\TwigComponent;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\Validator\SCSSValidator;
use Symfony\Component\Asset\Package as AssetPackage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem as LocalFilesystem;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

#[Package('framework')]
class ThemeCompiler implements ThemeCompilerInterface
{
    /**
     * @internal
     *
     * @param array<string, AssetPackage> $packages
     * @param array<int, string> $customAllowedRegex
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly FilesystemOperator $tempFilesystem,
        private readonly CopyBatchInputFactory $copyBatchInputFactory,
        private readonly ThemeFileResolver $themeFileResolver,
        private readonly TwigComponentHelper $twigComponentHelper,
        private readonly bool $debug,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ThemeFilesystemResolver $themeFilesystemResolver,
        private readonly iterable $packages,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly LoggerInterface $logger,
        private readonly AbstractThemePathBuilder $themePathBuilder,
        private readonly AbstractScssCompiler $scssCompiler,
        private readonly string $storefrontJsDir,
        private readonly array $customAllowedRegex = [],
        private readonly bool $validate = false,
        private readonly string $visibility = Visibility::PUBLIC,
        private readonly LocalFilesystem $localFilesystem = new LocalFilesystem(),
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
        $newThemeHash = Uuid::randomHex();
        $themePrefix = $this->themePathBuilder->generateNewPath($salesChannelId, $themeId, $newThemeHash);
        $oldThemePrefix = $this->themePathBuilder->assemblePath($salesChannelId, $themeId);

        // If the system does not use seeded theme paths,
        // we have to delete the complete folder before to ensure that old files are deleted
        if ($oldThemePrefix === $themePrefix) {
            $path = 'theme' . \DIRECTORY_SEPARATOR . $themePrefix;

            $this->filesystem->deleteDirectory($path);
        }

        // Normal style files. Loaded for usual pages.
        $compiledStyles = $this->getCompiledStyles(
            $this->getResolvedStyleFiles($themeConfig, $configurationCollection),
            $themeId,
            $themeConfig,
            $salesChannelId,
            $context
        );

        try {
            $styleCopyFiles = $this->getStyleCopyFiles($themePrefix, $compiledStyles);

            $assetCopyFiles = [];
            if ($withAssets) {
                $assetCopyFiles = $this->getAssetCopyFiles($themeConfig, $configurationCollection, $themeId);
            }
        } catch (\Throwable $e) {
            throw ThemeException::themeCompileException(
                $themeConfig->getName() ?? '',
                'Error while trying to write compiled files: ' . $e->getMessage(),
                $e
            );
        }

        $scriptFiles = $this->getScriptCopyFiles($configurationCollection, $themePrefix);

        CopyBatch::copy(
            $this->filesystem,
            ...$styleCopyFiles,
            ...$assetCopyFiles,
            ...$scriptFiles,
        );

        $this->themePathBuilder->saveSeed($salesChannelId, $themeId, $newThemeHash);

        $this->cacheInvalidator->invalidate([
            ThemeConfigCacheInvalidator::buildCacheTag($themeId),
        ]);
    }

    /**
     * @param array<string, string> $resolveMappings
     */
    public function getResolveImportPathsCallback(array $resolveMappings): \Closure
    {
        return function (string $originalPath) use ($resolveMappings): ?string {
            foreach ($resolveMappings as $resolve => $resolvePath) {
                $resolve = '~' . $resolve;
                if (mb_strpos($originalPath, $resolve) === 0) {
                    $dirname = $resolvePath . \dirname(mb_substr($originalPath, mb_strlen($resolve)));

                    $filename = basename($originalPath);
                    $extension = $this->getImportFileExtension(pathinfo($filename, \PATHINFO_EXTENSION));
                    $path = $dirname . \DIRECTORY_SEPARATOR . $filename . $extension;
                    if (\is_file($path)) {
                        return $path;
                    }

                    $path = $dirname . \DIRECTORY_SEPARATOR . '_' . $filename . $extension;
                    if (\is_file($path)) {
                        return $path;
                    }
                }
            }

            return null;
        };
    }

    /**
     * {@inheritdoc}
     *
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>}|null
     */
    public function buildComponentImportMap(string $salesChannelId, string $themeId): ?array
    {
        // The shopware runtime module is the prerequisite for the whole component system.
        $shopwareRuntimePath = $this->storefrontJsDir . '/dist-es/shopware/shopware.js';
        if (!$this->localFilesystem->exists($shopwareRuntimePath)) {
            return null;
        }

        // Build the URL prefix once: {assetBaseUrl}/theme/{themePathHash}
        // The theme path hash already changes on every recompile, so the URLs are
        // implicitly cache-busted without needing a per-file version query string.
        $themeBaseUrl = '';
        foreach ($this->packages as $key => $package) {
            if ($key === 'theme') {
                $themeBaseUrl = $package->getUrl('');
                break;
            }
        }
        $urlPrefix = $themeBaseUrl . '/theme/' . $this->themePathBuilder->assemblePath($salesChannelId, $themeId);
        $toUrl = static fn (string $relativePath): string => $urlPrefix . '/' . $relativePath;

        $imports = [];
        $scopes = [];

        // Core vendor map → top-level specifier imports.
        $coreVendorMap = $this->readVendorMap($this->storefrontJsDir);
        if ($coreVendorMap !== null) {
            foreach ($coreVendorMap as $specifier => $chunkPath) {
                $imports[$specifier] = $toUrl('js/components/' . $chunkPath);
            }
        }

        // The shopware singleton is always a shared top-level import.
        $imports['shopware'] = $toUrl('js/shopware/shopware.js');

        foreach ($this->groupComponentsByStorefrontDir() as $storefrontDir => $components) {
            $namespace = $components[0]->namespace;
            $isCore = $namespace === 'Storefront';
            $hasViteBuild = $this->hasViteBuild($storefrontDir);

            if (!$isCore) {
                // Extension vendor map → scoped specifier imports under the extension's URL prefix.
                $extVendorMap = $this->readVendorMap($storefrontDir);
                if ($extVendorMap !== null && $extVendorMap !== []) {
                    $scopeKey = $toUrl('js/components/' . $namespace . '/');
                    foreach ($extVendorMap as $specifier => $chunkPath) {
                        $scopes[$scopeKey][$specifier] = $toUrl('js/components/' . $chunkPath);
                    }
                }
            }

            foreach ($components as $component) {
                $hasScript = $hasViteBuild || $this->localFilesystem->exists($component->getScriptPath());
                if (!$hasScript) {
                    continue;
                }

                $relativePath = 'js/components/'
                    . str_replace(\DIRECTORY_SEPARATOR, '/', $component->getRelativeNamespacePath())
                    . '.js';

                $imports[$component->getTag()] = $toUrl($relativePath);
            }
        }

        $result = ['imports' => $imports];

        if ($scopes !== []) {
            $result['scopes'] = $scopes;
        }

        return $result;
    }

    /**
     * @return list<CopyBatchInput>
     */
    private function copyScriptFilesToTheme(
        StorefrontPluginConfigurationCollection $configurationCollection,
        string $themePrefix
    ): array {
        // The "getScriptDistFolders" method can remove script files from the scriptFiles property in the configurationCollection.
        // This can result in plugin script files being missing from later methods. Cloning the collection prevents this.
        // As structs are overriding the object cloning with the "CloneTrait" and implement a deep copy mechanism,
        // cloning the collection will prevent the mutation of the configurations and file collections inside as well.
        $scriptsDist = $this->getScriptDistFolders(clone $configurationCollection);
        $themePath = 'theme/' . $themePrefix;
        $distRelativePath = 'Resources/app/storefront/dist/storefront';

        $copyFiles = [];

        foreach ($scriptsDist as $folderName => $pluginConfig) {
            // For themes, we get basePath with Resources and for Plugins without, so we always remove and add it again
            $pathToJsFiles = $distRelativePath;
            if ($folderName !== 'storefront') {
                $pathToJsFiles .= '/js/' . $folderName;
            }

            $fs = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($pluginConfig);

            if ($fs->has($pathToJsFiles)) {
                $pathToJsFiles = $fs->realpath($pathToJsFiles);
            }

            $files = $this->getScriptDistFiles($pathToJsFiles);

            if ($files === null) {
                continue;
            }

            $targetPath = $themePath . '/js/' . $folderName;
            foreach ($files as $file) {
                $filePath = $file->getRealPath();
                if ($filePath) {
                    $copyFiles[] = new CopyBatchInput($filePath, [$targetPath . '/' . $file->getFilename()], $this->visibility);
                }
            }
        }

        return $copyFiles;
    }

    /**
     * @return list<CopyBatchInput>
     */
    private function copyComponentScriptFiles(string $themePrefix): array
    {
        $themeJsPath = 'theme/' . $themePrefix . '/js/';
        $themeComponentsPath = $themeJsPath . 'components/';

        $copyFiles = [];

        foreach ($this->groupComponentsByStorefrontDir() as $storefrontDir => $components) {
            $isCore = $components[0]->namespace === 'Storefront';
            $distComponentsDir = $storefrontDir . '/dist-es/components/';

            if ($this->hasViteBuild($storefrontDir)) {
                // Vite build present: all files are already in the correct namespace-prefixed
                // structure, so a flat recursive copy is all that is needed.
                foreach ((new Finder())->files()->in($distComponentsDir) as $file) {
                    $relativePath = $file->getRelativePathname();
                    if (str_starts_with($relativePath, '.vite' . \DIRECTORY_SEPARATOR)) {
                        continue;
                    }
                    $copyFiles[] = new CopyBatchInput(
                        $file->getPathname(),
                        [$themeComponentsPath . str_replace(\DIRECTORY_SEPARATOR, '/', $relativePath)],
                        $this->visibility
                    );
                }

                // Only core Storefront ships the shopware runtime module.
                if ($isCore) {
                    $shopwareSrc = $storefrontDir . '/dist-es/shopware/shopware.js';
                    if ($this->localFilesystem->exists($shopwareSrc)) {
                        $copyFiles[] = new CopyBatchInput(
                            $shopwareSrc,
                            [$themeJsPath . 'shopware/shopware.js'],
                            $this->visibility
                        );
                    }
                }

                continue;
            }

            // No Vite build: fall back to raw source files for each component.
            foreach ($components as $component) {
                $sourcePath = $component->getScriptPath();
                if (!$this->localFilesystem->exists($sourcePath)) {
                    continue;
                }
                $targetRelPath = str_replace(\DIRECTORY_SEPARATOR, '/', $component->getRelativeNamespacePath()) . '.js';
                $copyFiles[] = new CopyBatchInput($sourcePath, [$themeComponentsPath . $targetRelPath], $this->visibility);
            }
        }

        return $copyFiles;
    }

    /**
     * Returns true when the given storefront directory contains a complete Vite component build
     * (i.e. dist-es/components/.vite/manifest.json exists).
     *
     * manifest.json is always emitted by Vite when `manifest: true` is set in the config, even
     * when there are no external npm dependencies (which would mean no vendor-map.json).
     * Using it as the canonical "build present" signal is therefore more reliable than checking
     * for vendor-map.json, which is only emitted when vendor chunks exist.
     */
    private function hasViteBuild(string $storefrontDir): bool
    {
        return $this->localFilesystem->exists($storefrontDir . '/dist-es/components/.vite/manifest.json');
    }

    /**
     * Groups all registered Twig components by their storefront directory.
     *
     * Components carry their storefrontDir set at discovery time (TwigComponentBundlePass
     * for bundles, TwigComponentHelper for apps), so no path-parsing is needed here.
     *
     * @return array<string, list<TwigComponent>>
     */
    private function groupComponentsByStorefrontDir(): array
    {
        $groups = [];

        foreach ($this->twigComponentHelper->getComponents() as $component) {
            $storefrontDir = $component->storefrontDir !== '' ? $component->storefrontDir : $this->storefrontJsDir;
            $groups[$storefrontDir][] = $component;
        }

        return $groups;
    }

    /**
     * Reads the vendor-map.json emitted by componentMapPlugin for a given bundle's
     * storefront directory.  Returns null if no Vite build is present or the file
     * cannot be parsed.
     *
     * The file is a flat specifier → chunk-path map, e.g.:
     *   { "debounce": "ComponentTestApp/vendor/debounce-abc123.js" }
     *
     * This is the only build artefact PHP cannot derive on its own — the content-hashed
     * chunk filename is opaque without it.
     *
     * @return array<string, string>|null
     */
    private function readVendorMap(string $storefrontDir): ?array
    {
        $path = $storefrontDir . '/dist-es/components/.vite/vendor-map.json';

        if (!$this->localFilesystem->exists($path)) {
            return null;
        }

        try {
            $content = $this->localFilesystem->readFile($path);

            return json_decode($content, true, 512, \JSON_THROW_ON_ERROR);
        } catch (IOException|\JsonException) {
            return null;
        }
    }

    /**
     * @return array<string, StorefrontPluginConfiguration>
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

            $scriptsDistFolders[$configuration->getAssetName()] = $configuration;
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

            $fs = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($configuration);
            if ($asset[0] !== '/' && $fs->has('Resources', $asset)) {
                $asset = $fs->path('Resources', $asset);
            }

            $collected = [...$collected, ...$this->copyBatchInputFactory->fromDirectory($asset, $outputPath, $this->visibility)];
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
            throw ThemeException::themeCompileException(
                $configuration->getTechnicalName() . ' - Theme-ID: ' . $themeId,
                $exception->getMessage(),
                $exception,
            );
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

        $featuresScss = implode(',', array_map(static fn ($value, $key) => \sprintf('"%s": %s', $key, json_encode($value, \JSON_THROW_ON_ERROR)), $allFeatures, array_keys($allFeatures)));

        return \sprintf('$sw-features: (%s);', $featuresScss);
    }

    /**
     * Creates the strings that will be written to the SCSS file.
     * If variables have no or nullish value they will be written as "null" in SCSS.
     *
     * @param array<string, string|int|null> $variables
     *
     * @return array<string>
     */
    private function formatVariables(array $variables): array
    {
        return array_map(static fn ($value, $key) => \sprintf(
            '$%s: %s;',
            $key,
            isset($value) && $value !== '' ? $value : 'null'
        ), $variables, array_keys($variables));
    }

    /**
     * @param array{fields?: array{value: string|array<mixed>|null, scss?: bool, type: string}[]} $config
     *
     * @throws FilesystemException
     */
    private function dumpVariables(array $config, string $themeId, string $salesChannelId, Context $context): string
    {
        $variables = [
            'theme-id' => $themeId,
        ];

        foreach ($config['fields'] ?? [] as $key => $data) {
            if (
                !\is_array($data)
                || (\array_key_exists('scss', $data) && $data['scss'] === false)
                || !isset($data['type'])
            ) {
                continue;
            }

            if ($this->validate) {
                $data['value'] = SCSSValidator::validate($this->scssCompiler, $data, $this->customAllowedRegex, true);
            }

            if (!\array_key_exists('value', $data)) {
                // If a variable does not exist, it should still be written with a null value.
                $variables[$key] = null;
                continue;
            }

            if (
                \in_array($data['type'], ['media', 'textarea', 'url'], true)
                && \is_string($data['value'])
                && !\str_starts_with($data['value'], '\'')
                && !\str_ends_with($data['value'], '\'')
            ) {
                $variables[$key] = '\'' . $data['value'] . '\'';
            } elseif ($data['type'] === 'switch' || $data['type'] === 'checkbox') {
                $variables[$key] = (int) $data['value'];
            } elseif (!\is_array($data['value'])) {
                $variables[$key] = (string) $data['value'];
            }
        }

        foreach ($this->packages as $key => $package) {
            $variables[\sprintf('sw-asset-%s-url', $key)] = \sprintf('\'%s\'', $package->getUrl(''));
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
        $this->tempFilesystem->write('theme-variables/' . $themeId . '.scss', $dump);

        return $dump;
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
        $styles = $styleFiles->map(static fn (File $file) => \sprintf('@import \'%s\';', $file->getFilepath()));

        $concatenatedStylesEvent = new ThemeCompilerConcatenatedStylesEvent(
            implode("\n", $styles),
            $salesChannelId
        );
        $this->eventDispatcher->dispatch($concatenatedStylesEvent);

        return $concatenatedStylesEvent->getConcatenatedStyles();
    }

    private function getResolvedStyleFiles(
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
    ): FileCollection {
        try {
            return $this->themeFileResolver->resolveStyleFiles($themeConfig, $configurationCollection, false);
        } catch (\Throwable $e) {
            throw ThemeException::themeCompileException(
                $themeConfig->getName() ?? '',
                'Files could not be resolved with error: ' . $e->getMessage(),
                $e
            );
        }
    }

    /**
     * Concatenates all files of the provided collection and compiles the styles.
     */
    private function getCompiledStyles(
        FileCollection $styleFiles,
        string $themeId,
        StorefrontPluginConfiguration $themeConfig,
        string $salesChannelId,
        Context $context,
    ): string {
        try {
            $concatenatedStyles = $this->concatenateStyles($styleFiles, $salesChannelId);
        } catch (\Throwable $e) {
            throw ThemeException::themeCompileException(
                $themeConfig->getName() ?? '',
                'Error while trying to concatenate Styles: ' . $e->getMessage(),
                $e
            );
        }

        return $this->compileStyles(
            $concatenatedStyles,
            $themeConfig,
            $styleFiles->getResolveMappings(),
            $salesChannelId,
            $themeId,
            $context
        );
    }

    /**
     * @return list<CopyBatchInput>
     */
    private function getStyleCopyFiles(
        string $themePrefix,
        string $compiled,
        string $fileName = 'all.css'
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
                    $compileLocation . \DIRECTORY_SEPARATOR . 'css' . \DIRECTORY_SEPARATOR . $fileName,
                ],
                $this->visibility
            ),
        ];

        return $files;
    }

    /**
     * @return list<CopyBatchInput>
     */
    private function getAssetCopyFiles(
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        string $themeId
    ): array {
        $assetPath = 'theme' . \DIRECTORY_SEPARATOR . $themeId;

        try {
            $this->filesystem->deleteDirectory($assetPath);
        } catch (UnableToDeleteDirectory) {
        }

        return $this->getAssets($themeConfig, $configurationCollection, $assetPath);
    }

    /**
     * @return list<CopyBatchInput>
     */
    private function getScriptCopyFiles(
        StorefrontPluginConfigurationCollection $configurationCollection,
        string $themePrefix
    ): array {
        $themeScriptCopyFiles = $this->copyScriptFilesToTheme($configurationCollection, $themePrefix);
        $componentScriptCopyFiles = $this->copyComponentScriptFiles($themePrefix);

        return array_values([...$themeScriptCopyFiles, ...$componentScriptCopyFiles]);
    }
}

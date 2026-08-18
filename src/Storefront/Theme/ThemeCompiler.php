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
use Shopware\Storefront\Theme\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\Validator\SCSSValidator;
use Symfony\Component\Asset\Package as AssetPackage;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\Exception\DirectoryNotFoundException;
use Symfony\Component\Finder\Finder;

#[Package('discovery')]
class ThemeCompiler implements ThemeCompilerInterface
{
    /**
     * @var array<string, AssetPackage>
     */
    private array $packages;

    /**
     * @var array<string, array{
     *     manifest: array<string, array{file?: string, name?: string, src?: string, isEntry?: bool, css?: list<string>}>,
     *     vendorMap: array<string, string>
     * }|null>
     */
    private array $bundleBuildMetaCache = [];

    /**
     * @internal
     *
     * @param iterable<string, AssetPackage> $packages
     * @param array<int, string> $customAllowedRegex
     */
    public function __construct(
        private readonly FilesystemOperator $filesystem,
        private readonly FilesystemOperator $tempFilesystem,
        private readonly FilesystemOperator $assetFilesystem,
        private readonly CopyBatchInputFactory $copyBatchInputFactory,
        private readonly ThemeFileResolver $themeFileResolver,
        private readonly bool $debug,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ThemeFilesystemResolver $themeFilesystemResolver,
        iterable $packages,
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly LoggerInterface $logger,
        private readonly AbstractThemePathBuilder $themePathBuilder,
        private readonly AbstractScssCompiler $scssCompiler,
        private readonly array $customAllowedRegex = [],
        private readonly bool $validate = false,
        private readonly string $visibility = Visibility::PUBLIC,
    ) {
        $this->packages = \is_array($packages) ? $packages : iterator_to_array($packages);
    }

    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $withAssets,
        Context $context
    ): void {
        // Normal style files. Loaded for usual pages.
        $compiledStyles = $this->getCompiledStyles(
            $this->getResolvedStyleFiles($themeConfig, $configurationCollection),
            $themeId,
            $themeConfig,
            $salesChannelId,
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
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>, styles?: list<string>}|null
     */
    public function buildComponentImportMap(
        ?StorefrontPluginConfigurationCollection $configurationCollection = null,
    ): ?array {
        // Keep this cache scoped to a single import-map build.
        $this->bundleBuildMetaCache = [];

        $imports = [];
        $scopes = [];
        $styles = [];

        $bundleNames = $this->resolveBundleNames($configurationCollection);

        // Core vendor chunks → top-level specifier imports from bundle asset URLs.
        $coreVendorMap = $this->readBundleBuildMeta('Storefront', $configurationCollection)['vendorMap'] ?? [];
        foreach ($coreVendorMap as $specifier => $chunkPath) {
            $imports[$specifier] = '/bundles/' . $this->toAssetDirectory('Storefront') . '/storefront/components/' . $chunkPath;
        }

        // The shopware singleton is published as a normal bundle asset.
        $imports['shopware'] = '/bundles/' . $this->toAssetDirectory('Storefront') . '/storefront/shopware/shopware.js';

        // Component entries (with content-hashed filenames) come from per-bundle
        // build metadata in `public/bundles/<bundle>/storefront/components/.vite/build-meta.json`.
        $componentManifest = $this->collectComponentManifestEntries($bundleNames, $configurationCollection);
        foreach ($componentManifest as $tag => $entry) {
            $bundleName = $entry['bundle'];
            if (isset($entry['js']) && $entry['js'] !== '') {
                $imports[$tag] = $entry['js'];
            }
            if (isset($entry['css']) && $entry['css'] !== []) {
                foreach ($entry['css'] as $cssPath) {
                    $styles[] = $cssPath;
                }
            }
        }

        // Extension vendor maps → scoped specifier imports so that vendor chunks
        // are only resolved when inside that extension's component scope.
        $scopes = $this->buildExtensionVendorScopes($bundleNames, $configurationCollection);

        $result = ['imports' => $imports];

        if ($scopes !== []) {
            $result['scopes'] = $scopes;
        }

        if ($styles !== []) {
            $result['styles'] = $styles;
        }

        return $result;
    }

    /**
     * @deprecated tag:v6.8.0 - Build-meta loading no longer fetches public URLs.
     * Keep as protected no-op for backwards compatibility.
     */
    protected function fetchPublicFile(string $url): string|false
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0')
        );

        return false;
    }

    private function readBuildMetaFile(string $relativeMetaPath): ?string
    {
        $filesystemPath = ltrim($relativeMetaPath, '/');

        try {
            $raw = $this->assetFilesystem->read($filesystemPath);

            return $raw !== '' ? $raw : null;
        } catch (FilesystemException) {
            return null;
        }
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
     * Collects component import-map entries from all active bundle manifests.
     *
     * @param list<string> $bundleNames
     *
     * @return array<string, array{bundle: string, js?: string, css?: list<string>}>
     */
    private function collectComponentManifestEntries(
        array $bundleNames,
        ?StorefrontPluginConfigurationCollection $configurationCollection = null,
    ): array {
        $manifest = [];
        foreach ($bundleNames as $bundleName) {
            $bundleManifest = $this->readBundleComponentManifest($bundleName, $configurationCollection);
            if ($bundleManifest === null) {
                continue;
            }

            foreach ($bundleManifest as $tag => $entry) {
                $manifest[$tag] = $entry;
            }
        }

        return $manifest;
    }

    /**
     * @return array<string, array{bundle: string, js?: string, css?: list<string>}>|null
     */
    private function readBundleComponentManifest(
        string $bundleName,
        ?StorefrontPluginConfigurationCollection $configurationCollection = null,
    ): ?array {
        $buildMeta = $this->readBundleBuildMeta($bundleName, $configurationCollection);
        if ($buildMeta === null || $buildMeta['manifest'] === []) {
            return null;
        }

        $viteManifest = $buildMeta['manifest'];
        $jsToCssFiles = $this->collectJsToCssFiles($viteManifest);

        $result = [];
        $publicBase = '/bundles/' . $this->toAssetDirectory($bundleName) . '/storefront/components/';

        foreach ($viteManifest as $entry) {
            if (($entry['isEntry'] ?? false) !== true || !isset($entry['name']) || $entry['name'] === '' || !isset($entry['file'])) {
                continue;
            }

            $entryName = preg_replace('/\.(scss|css)$/', '', $entry['name']) ?? $entry['name'];
            $outputFile = $entry['file'];
            $tag = str_replace('/', ':', $entryName);

            if (str_ends_with($outputFile, '.css')) {
                $result[$tag]['bundle'] = $bundleName;
                $result[$tag]['css'][] = $publicBase . $outputFile;
            } elseif (str_ends_with($outputFile, '.js')) {
                $result[$tag]['bundle'] = $bundleName;
                $result[$tag]['js'] = $publicBase . $outputFile;

                if (isset($jsToCssFiles[$entryName])) {
                    foreach ($jsToCssFiles[$entryName] as $cssFile) {
                        $result[$tag]['css'][] = $publicBase . $cssFile;
                    }
                }
            }
        }

        foreach ($result as $tag => $entry) {
            if (!isset($entry['css'])) {
                continue;
            }
            $result[$tag]['css'] = array_values(array_unique($entry['css']));
        }

        return $result;
    }

    /**
     * @param array<string, array{file?: string, name?: string, src?: string, isEntry?: bool, css?: list<string>}> $viteManifest
     *
     * @return array<string, list<string>>
     */
    private function collectJsToCssFiles(array $viteManifest): array
    {
        $jsToCssFiles = [];
        foreach ($viteManifest as $entry) {
            if (($entry['isEntry'] ?? false) !== true || !isset($entry['name']) || $entry['name'] === '') {
                continue;
            }
            if (isset($entry['css']) && $entry['css'] !== []) {
                $jsToCssFiles[$entry['name']] = $entry['css'];
            }
        }

        return $jsToCssFiles;
    }

    /**
     * @return array{
     *     manifest: array<string, array{file?: string, name?: string, src?: string, isEntry?: bool, css?: list<string>}>,
     *     vendorMap: array<string, string>
     * }|null
     */
    private function readBundleBuildMeta(
        string $bundleName,
        ?StorefrontPluginConfigurationCollection $configurationCollection = null,
    ): ?array {
        if (\array_key_exists($bundleName, $this->bundleBuildMetaCache)) {
            return $this->bundleBuildMetaCache[$bundleName];
        }

        $relativeMetaPath = $this->getPublishedComponentsRoot($bundleName) . '/.vite/build-meta.json';
        $raw = $this->readBuildMetaFile($relativeMetaPath);
        if ($raw === null || $raw === '') {
            return $this->bundleBuildMetaCache[$bundleName] = null;
        }

        try {
            $decoded = json_decode($raw, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->bundleBuildMetaCache[$bundleName] = null;
        }

        return $this->bundleBuildMetaCache[$bundleName] = $this->normalizeBundleBuildMeta($decoded);
    }

    /**
     * @return array{
     *     manifest: array<string, array{file?: string, name?: string, src?: string, isEntry?: bool, css?: list<string>}>,
     *     vendorMap: array<string, string>
     * }
     */
    private function normalizeBundleBuildMeta(mixed $decoded): array
    {
        if (!\is_array($decoded)) {
            return [
                'manifest' => [],
                'vendorMap' => [],
            ];
        }

        $manifest = [];
        if (isset($decoded['manifest']) && \is_array($decoded['manifest'])) {
            $manifest = $decoded['manifest'];
        }

        $vendorMap = [];
        if (isset($decoded['vendorMap']) && \is_array($decoded['vendorMap'])) {
            $vendorMap = $decoded['vendorMap'];
        }

        return [
            'manifest' => $manifest,
            'vendorMap' => $vendorMap,
        ];
    }

    /**
     * @param list<string> $bundleNames
     *
     * @return array<string, array<string, string>>
     */
    private function buildExtensionVendorScopes(
        array $bundleNames,
        ?StorefrontPluginConfigurationCollection $configurationCollection = null,
    ): array {
        $scopes = [];

        foreach ($bundleNames as $bundleName) {
            if ($bundleName === 'Storefront') {
                continue;
            }

            $vendorMap = $this->readBundleBuildMeta($bundleName, $configurationCollection)['vendorMap'] ?? [];
            if ($vendorMap === []) {
                continue;
            }

            $bundleComponentsBase = '/bundles/' . $this->toAssetDirectory($bundleName) . '/storefront/components/';
            $scopeKey = $bundleComponentsBase . $bundleName . '/';

            foreach ($vendorMap as $specifier => $chunkPath) {
                $scopes[$scopeKey][$specifier] = $bundleComponentsBase . $chunkPath;
            }
        }

        return $scopes;
    }

    /**
     * @return list<string>
     */
    private function resolveBundleNames(?StorefrontPluginConfigurationCollection $configurationCollection): array
    {
        if ($configurationCollection === null) {
            return ['Storefront'];
        }

        $bundleNames = ['Storefront'];

        foreach ($configurationCollection as $configuration) {
            $bundleNames[] = $configuration->getTechnicalName();
        }

        return array_values(array_unique($bundleNames));
    }

    private function getPublishedComponentsRoot(string $bundleName): string
    {
        return 'bundles/' . $this->toAssetDirectory($bundleName) . '/storefront/components';
    }

    private function toAssetDirectory(string $bundleName): string
    {
        return preg_replace('/bundle$/', '', strtolower($bundleName)) ?? strtolower($bundleName);
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

        $fs = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($configuration);

        foreach ($configuration->getAssetPaths() as $asset) {
            if (str_starts_with($asset, '@')) {
                $name = mb_substr($asset, 1);
                $config = $configurationCollection->getByTechnicalName($name);
                if (!$config) {
                    throw ThemeException::couldNotFindThemeByName($name);
                }

                $collected = [...$collected, ...$this->getAssets($config, $configurationCollection, $outputPath)];

                continue;
            }

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
     * @param array{
     *     fields?: array<string, array{
     *         value?: array<mixed>|bool|float|int|string|null,
     *         scss?: bool|null,
     *         type?: string|null,
     *         ...<string, mixed>
     *     }>,
     *     ...<string, mixed>
     * } $config
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
        return $this->copyScriptFilesToTheme($configurationCollection, $themePrefix);
    }
}

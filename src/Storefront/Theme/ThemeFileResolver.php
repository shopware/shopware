<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\ThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

#[Package('framework')]
class ThemeFileResolver
{
    final public const SCRIPT_FILES = 'script';
    final public const STYLE_FILES = 'style';

    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeFilesystemResolver $themeFilesystemResolver,
    ) {
    }

    /**
     * @return array<string, FileCollection>
     */
    public function resolveFiles(
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles
    ): array {
        return [
            self::SCRIPT_FILES => $this->resolveScriptFiles(
                $themeConfig,
                $configurationCollection,
                $onlySourceFiles
            ),
            self::STYLE_FILES => $this->resolveStyleFiles(
                $themeConfig,
                $configurationCollection,
                $onlySourceFiles
            ),
        ];
    }

    public function resolveScriptFiles(
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles
    ): FileCollection {
        return $this->resolve(
            self::SCRIPT_FILES,
            $themeConfig,
            $configurationCollection,
            $onlySourceFiles,
            $this->collectConfigurationScriptFiles(...),
            [],
            [],
            []
        );
    }

    public function resolveStyleFiles(
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles
    ): FileCollection {
        return $this->resolve(
            self::STYLE_FILES,
            $themeConfig,
            $configurationCollection,
            $onlySourceFiles,
            static fn (StorefrontPluginConfiguration $configuration) => $configuration->getStyleFiles(),
            [],
            [],
            []
        );
    }

    private function collectConfigurationScriptFiles(StorefrontPluginConfiguration $configuration, bool $onlySourceFiles): FileCollection
    {
        $fileCollection = new FileCollection();
        $scriptFiles = $configuration->getScriptFiles();
        $addSourceFile = $configuration->getStorefrontEntryFilepath() && $onlySourceFiles;

        // add source file at the beginning if no other theme is included first
        if ($addSourceFile
            && $configuration->getStorefrontEntryFilepath()
            && ($scriptFiles->count() === 0 || !$scriptFiles->first() || !$this->isInclude($scriptFiles->first()->getFilepath()))
        ) {
            $fileCollection->add(new File($configuration->getStorefrontEntryFilepath()));
        }
        foreach ($scriptFiles as $scriptFile) {
            if ($onlySourceFiles && !$this->isInclude($scriptFile->getFilepath())) {
                continue;
            }
            $fileCollection->add($scriptFile);
        }
        if ($addSourceFile
            && $configuration->getStorefrontEntryFilepath()
            && $scriptFiles->count() > 0
            && $scriptFiles->first()
            && $this->isInclude($scriptFiles->first()->getFilepath())
        ) {
            $fileCollection->add(new File($configuration->getStorefrontEntryFilepath()));
        }

        foreach ($fileCollection as $file) {
            $file->assetName = $configuration->getAssetName();
        }

        return $fileCollection;
    }

    /**
     * Resolves theme files by processing both direct file paths and namespaced imports
     *
     * @param StorefrontPluginConfiguration $themeConfig The theme configuration to resolve files for
     * @param StorefrontPluginConfigurationCollection $configurationCollection Collection of all available theme configurations
     * @param bool $onlySourceFiles Whether to only include source files (true) or also compiled files (false)
     * @param callable(StorefrontPluginConfiguration, bool): FileCollection $configFileResolver Function to get the initial file collection (either style or script files)
     * @param array<int, string> $included List of already included namespaces to prevent duplicates
     * @param array<string, bool> $processedFiles List of already processed absolute file paths to prevent duplicates
     * @param array<string, bool> $processedConfigs List of already processed configuration names to prevent circular references
     *
     * @return FileCollection Collection of resolved files
     */
    private function resolve(
        string $fileType,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles,
        callable $configFileResolver,
        array $included = [],
        array $processedFiles = [],
        array $processedConfigs = []
    ): FileCollection {
        $configName = $themeConfig->getTechnicalName();
        if (isset($processedConfigs[$configName])) {
            return new FileCollection();
        }
        $nextProcessedConfigs = [...$processedConfigs, $configName => true];

        // convertPathsToAbsolute changes the path, this should not affect the passed configuration
        $themeConfig = clone $themeConfig;

        $files = $configFileResolver($themeConfig, $onlySourceFiles);

        if ($files->count() === 0) {
            return $files;
        }

        $this->convertPathsToAbsolute($themeConfig, $files);

        $resolvedFiles = new FileCollection();
        $nextIncluded = $this->collectNamespaceIncludes($files, $included);

        foreach ($files as $file) {
            $filepath = $file->getFilepath();
            $bundleRelative = $this->parseBundleRelativePath($filepath);

            if ($bundleRelative !== null) {
                $this->processBundleRelativeFile($filepath, $bundleRelative, $fileType, $themeConfig, $configurationCollection, $resolvedFiles, $processedFiles);
            } elseif (!$this->isInclude($filepath)) {
                $this->processDirectFile($file, $filepath, $themeConfig, $resolvedFiles, $processedFiles);
            } else {
                $this->processNamespaceReference($filepath, $fileType, $themeConfig, $configurationCollection, $onlySourceFiles, $configFileResolver, $nextIncluded, $included, $processedFiles, $nextProcessedConfigs, $resolvedFiles);
            }
        }

        return $resolvedFiles;
    }

    /**
     * Pre-scans a file list and appends any simple @Namespace entries (no slash) to the included list.
     * The result is passed to recursive resolve() calls so child resolutions know what is already planned.
     *
     * @param array<int, string> $included
     *
     * @return array<int, string>
     */
    private function collectNamespaceIncludes(FileCollection $files, array $included): array
    {
        foreach ($files as $file) {
            $filepath = $file->getFilepath();
            if ($this->isInclude($filepath) && !str_contains($filepath, '/')) {
                $included[] = $filepath;
            }
        }

        return $included;
    }

    /**
     * Resolves a bundle-relative path reference (@BundleName/path).
     *
     * @param array{bundle: string, path: string} $bundleRelative
     * @param array<string, bool> $processedFiles
     */
    private function processBundleRelativeFile(
        string $filepath,
        array $bundleRelative,
        string $fileType,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        FileCollection $resolvedFiles,
        array &$processedFiles
    ): void {
        $bundleConfig = $configurationCollection->getByTechnicalName($bundleRelative['bundle']);
        if (!$bundleConfig) {
            throw ThemeException::couldNotFindThemeByName($bundleRelative['bundle']);
        }

        $fs = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($bundleConfig);
        if ($fs->has('Resources', $bundleRelative['path'])) {
            $absolutePath = $fs->realpath('Resources', $bundleRelative['path']);

            if (!isset($processedFiles[$absolutePath])) {
                $processedFiles[$absolutePath] = true;
                $resolvedFiles->add(new File($absolutePath, [], $bundleConfig->getAssetName()));
            }
        } else {
            throw ThemeException::themeCompileException(
                $themeConfig->getTechnicalName(),
                \sprintf('Unable to resolve file "%s". File does not exist.', $filepath)
            );
        }
    }

    /**
     * Resolves a direct (non-@) file path reference.
     * Silently skips files that match the old pre-async JS bundle structure.
     * Throws when a file is missing and does not match that structure.
     *
     * @param array<string, bool> $processedFiles
     */
    private function processDirectFile(
        File $file,
        string $filepath,
        StorefrontPluginConfiguration $themeConfig,
        FileCollection $resolvedFiles,
        array &$processedFiles
    ): void {
        if (\is_file($filepath)) {
            if (!isset($processedFiles[$filepath])) {
                $processedFiles[$filepath] = true;
                $resolvedFiles->add($file);
            }

            return;
        }

        // removes file with old js structure (before async changes) from collection
        if (!str_ends_with($filepath, $file->assetName . '/' . basename($filepath))) {
            return;
        }

        throw ThemeException::themeCompileException(
            $themeConfig->getTechnicalName(),
            \sprintf(
                'Unable to resolve file "Resources/%s". %s',
                $filepath,
                'Did you forget to build the theme? Try running ./bin/build-storefront.sh'
            )
        );
    }

    /**
     * Dispatches a @Namespace reference to the appropriate handler after deduplication.
     *
     * @param array<int, string> $nextIncluded Already-known includes for this level (passed to recursive calls)
     * @param array<int, string> $included Tracks which namespaces have been processed in the current loop
     * @param array<string, bool> $processedFiles
     * @param array<string, bool> $nextProcessedConfigs
     */
    private function processNamespaceReference(
        string $filepath,
        string $fileType,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles,
        callable $configFileResolver,
        array $nextIncluded,
        array &$included,
        array &$processedFiles,
        array $nextProcessedConfigs,
        FileCollection $resolvedFiles
    ): void {
        if (\in_array($filepath, $included, true)) {
            return;
        }
        $included[] = $filepath;

        if ($filepath === '@Plugins') {
            $this->addFilesFromPlugins($fileType, $configurationCollection, $onlySourceFiles, $configFileResolver, $nextIncluded, $processedFiles, $nextProcessedConfigs, $resolvedFiles);

            return;
        }

        if ($filepath === '@StorefrontBootstrap') {
            $this->addStorefrontBootstrapFile($processedFiles, $resolvedFiles);

            return;
        }

        $this->addFilesFromTheme($filepath, $fileType, $configurationCollection, $onlySourceFiles, $configFileResolver, $nextIncluded, $processedFiles, $nextProcessedConfigs, $resolvedFiles, $themeConfig);
    }

    /**
     * Resolves all non-theme plugins and appends their files.
     *
     * @param array<int, string> $nextIncluded
     * @param array<string, bool> $processedFiles
     * @param array<string, bool> $nextProcessedConfigs
     */
    private function addFilesFromPlugins(
        string $fileType,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles,
        callable $configFileResolver,
        array $nextIncluded,
        array &$processedFiles,
        array $nextProcessedConfigs,
        FileCollection $resolvedFiles
    ): void {
        foreach ($configurationCollection->getNoneThemes() as $plugin) {
            $items = $this->resolve($fileType, $plugin, $configurationCollection, $onlySourceFiles, $configFileResolver, $nextIncluded, $processedFiles, $nextProcessedConfigs);
            $this->addResolvedItems($items, $resolvedFiles, $processedFiles);
        }
    }

    /**
     * Appends the Storefront bootstrap SCSS entry point with its vendor mapping.
     *
     * @param array<string, bool> $processedFiles
     */
    private function addStorefrontBootstrapFile(array &$processedFiles, FileCollection $resolvedFiles): void
    {
        $bootstrapPath = __DIR__ . '/../Resources/app/storefront/src/scss/base.scss';
        if (!isset($processedFiles[$bootstrapPath])) {
            $processedFiles[$bootstrapPath] = true;
            $resolvedFiles->add(new File(
                $bootstrapPath,
                ['vendor' => __DIR__ . '/../Resources/app/storefront/vendor']
            ));
        }
    }

    /**
     * Recursively resolves a named @ThemeName reference and appends its files.
     *
     * @param array<int, string> $nextIncluded
     * @param array<string, bool> $processedFiles
     * @param array<string, bool> $nextProcessedConfigs
     */
    private function addFilesFromTheme(
        string $filepath,
        string $fileType,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles,
        callable $configFileResolver,
        array $nextIncluded,
        array &$processedFiles,
        array $nextProcessedConfigs,
        FileCollection $resolvedFiles,
        StorefrontPluginConfiguration $themeConfig
    ): void {
        $name = mb_substr($filepath, 1);
        $configuration = $configurationCollection->getByTechnicalName($name);
        if (!$configuration) {
            throw ThemeException::couldNotFindThemeByName($name);
        }

        $items = $this->resolve($fileType, $configuration, $configurationCollection, $onlySourceFiles, $configFileResolver, $nextIncluded, $processedFiles, $nextProcessedConfigs);
        $this->addResolvedItems($items, $resolvedFiles, $processedFiles);
    }

    /**
     * Adds items from a resolved FileCollection into $resolvedFiles, skipping already-processed paths.
     *
     * @param array<string, bool> $processedFiles
     */
    private function addResolvedItems(FileCollection $items, FileCollection $resolvedFiles, array &$processedFiles): void
    {
        foreach ($items as $item) {
            $itemPath = $item->getFilepath();
            if (!isset($processedFiles[$itemPath])) {
                $processedFiles[$itemPath] = true;
                $resolvedFiles->add($item);
            }
        }
    }

    private function isInclude(string $file): bool
    {
        return str_starts_with($file, '@');
    }

    /**
     * Check if filepath is a bundle-relative single file reference
     * Format: @BundleName/relative/path/to/file.ext
     *
     * @return array{bundle: string, path: string}|null
     */
    private function parseBundleRelativePath(string $filepath): ?array
    {
        if (!str_starts_with($filepath, '@')) {
            return null;
        }

        // Check if it contains a slash (indicates file path, not just bundle name)
        $slashPos = strpos($filepath, '/');
        if ($slashPos === false) {
            return null;
        }

        return [
            'bundle' => substr($filepath, 1, $slashPos - 1),
            'path' => substr($filepath, $slashPos + 1),
        ];
    }

    private function convertPathsToAbsolute(StorefrontPluginConfiguration $themeConfig, FileCollection $files): void
    {
        foreach ($files->getElements() as $file) {
            if ($this->isInclude($file->getFilepath())) {
                continue;
            }

            $fs = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($themeConfig);
            if ($fs->has('Resources', $file->getFilepath())) {
                $file->setFilepath($fs->realpath('Resources', $file->getFilepath()));
            }

            $mapping = $file->getResolveMapping();

            foreach ($mapping as $key => $val) {
                if ($fs->has('Resources', $val)) {
                    $mapping[$key] = $fs->realpath('Resources', $val);
                }
            }

            $file->setResolveMapping($mapping);
        }
    }
}

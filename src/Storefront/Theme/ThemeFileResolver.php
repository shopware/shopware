<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
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
    final public const BASE_STYLE_FILES = 'baseStyles';

    /**
     * @internal
     */
    public function __construct(
        private readonly ThemeFilesystemResolver $themeFilesystemResolver,
        private readonly TwigComponentHelper $twigComponentHelper
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
            self::BASE_STYLE_FILES => $this->resolveBaseStyleFiles(
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
            fn (StorefrontPluginConfiguration $configuration) => $configuration->getStyleFiles(),
            [],
            [],
            []
        );
    }

    public function resolveBaseStyleFiles(
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles
    ): FileCollection {
        return $this->resolve(
            self::BASE_STYLE_FILES,
            $themeConfig,
            $configurationCollection,
            $onlySourceFiles,
            fn (StorefrontPluginConfiguration $configuration) => $configuration->getBaseStyleFiles(),
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
        // Prevent circular configuration references
        $configName = $themeConfig->getTechnicalName();
        if (isset($processedConfigs[$configName])) {
            // Circular reference detected - return empty collection to break the loop
            return new FileCollection();
        }
        $nextProcessedConfigs = $processedConfigs;
        $nextProcessedConfigs[$configName] = true;

        // convertPathsToAbsolute changes the path, this should not affect the passed configuration
        $themeConfig = clone $themeConfig;

        // Get initial file collection using the provided resolver
        $files = $configFileResolver($themeConfig, $onlySourceFiles);

        // Return empty collection if no files found
        if ($files->count() === 0) {
            return $files;
        }

        // Convert all relative paths to absolute paths
        $this->convertPathsToAbsolute($themeConfig, $files);

        // Initialize collection for resolved files
        $resolvedFiles = new FileCollection();
        $nextIncluded = $included;

        // Collect namespace includes for tracking
        foreach ($files as $file) {
            $filepath = $file->getFilepath();
            if ($this->isInclude($filepath) && !str_contains($filepath, '/')) {
                // Only track simple @ namespace references (not bundle-relative paths)
                $nextIncluded[] = $filepath;
            }
        }

        // Process files in order from theme.json
        foreach ($files as $file) {
            $filepath = $file->getFilepath();

            // Handle bundle-relative single file references (@BundleName/path/to/file)
            $bundleRelative = $this->parseBundleRelativePath($filepath);
            if ($bundleRelative !== null) {
                // Handle @Components single file references
                if (str_starts_with($bundleRelative['bundle'], 'Components')) {
                    if (!Feature::isActive('STOREFRONT_COMPONENTS')) {
                        continue;
                    }

                    $resolvedComponentFile = $this->resolveComponentSingleFile($filepath, $fileType);

                    if ($resolvedComponentFile === null) {
                        throw ThemeException::themeCompileException(
                            $themeConfig->getTechnicalName(),
                            \sprintf(
                                'Unable to resolve file "%s". File does not exist.',
                                $filepath
                            )
                        );
                    }

                    if (!isset($processedFiles[$resolvedComponentFile->getFilepath()])) {
                        $processedFiles[$resolvedComponentFile->getFilepath()] = true;
                        $resolvedFiles->add($resolvedComponentFile);
                    }

                    continue;
                }

                // Handle regular bundle-relative files
                $bundleConfig = $configurationCollection->getByTechnicalName($bundleRelative['bundle']);
                if (!$bundleConfig) {
                    throw ThemeException::couldNotFindThemeByName($bundleRelative['bundle']);
                }

                // Resolve the specific file from the bundle
                $fs = $this->themeFilesystemResolver->getFilesystemForStorefrontConfig($bundleConfig);
                if ($fs->has('Resources', $bundleRelative['path'])) {
                    $absolutePath = $fs->realpath('Resources', $bundleRelative['path']);

                    // Skip if already processed
                    if (!isset($processedFiles[$absolutePath])) {
                        $processedFiles[$absolutePath] = true;
                        $resolvedFile = new File($absolutePath, [], $bundleConfig->getAssetName());
                        $resolvedFiles->add($resolvedFile);
                    }
                } else {
                    throw ThemeException::themeCompileException(
                        $themeConfig->getTechnicalName(),
                        \sprintf(
                            'Unable to resolve file "%s". File does not exist.',
                            $filepath
                        )
                    );
                }
                continue;
            }

            // Handle direct file paths (not starting with @)
            if (!$this->isInclude($filepath)) {
                if (\is_file($filepath)) {
                    // Skip if already processed
                    if (!isset($processedFiles[$filepath])) {
                        $processedFiles[$filepath] = true;
                        $resolvedFiles->add($file);
                    }
                    continue;
                }

                // removes file with old js structure (before async changes) from collection
                if (!str_ends_with($filepath, $file->assetName . '/' . basename($filepath))) {
                    continue;
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

            // Handle full bundle/namespace references (@BundleName, @Plugins, @Components, etc.)

            // Skip if this namespace was already included (maintain existing behavior)
            if (\in_array($filepath, $included, true)) {
                continue;
            }
            $included[] = $filepath;

            // Handle @Plugins
            if ($filepath === '@Plugins') {
                foreach ($configurationCollection->getNoneThemes() as $plugin) {
                    foreach ($this->resolve(
                        $fileType,
                        $plugin,
                        $configurationCollection,
                        $onlySourceFiles,
                        $configFileResolver,
                        $nextIncluded,
                        $processedFiles,
                        $nextProcessedConfigs
                    ) as $item) {
                        $itemPath = $item->getFilepath();
                        if (!isset($processedFiles[$itemPath])) {
                            $processedFiles[$itemPath] = true;
                            $resolvedFiles->add($item);
                        }
                    }
                }
                continue;
            }

            // Handle @Components
            if ($filepath === '@Components') {
                if (!Feature::isActive('STOREFRONT_COMPONENTS')) {
                    continue;
                }

                foreach ($this->twigComponentHelper->getComponents() as $component) {
                    $componentPath = $fileType === self::SCRIPT_FILES
                        ? $component->getScriptPath()
                        : $component->getStylePath();

                    if ($componentPath !== null && !isset($processedFiles[$componentPath])) {
                        $processedFiles[$componentPath] = true;
                        $namespaceDir = $component->getRelativeNamespaceDirectory();
                        $assetName = $namespaceDir !== '' ? $namespaceDir : null;
                        $resolvedFiles->add(new File($componentPath, [], $assetName));
                    }
                }

                continue;
            }

            // Handle @StorefrontBootstrap
            if ($filepath === '@StorefrontBootstrap') {
                $bootstrapPath = __DIR__ . '/../Resources/app/storefront/src/scss/base.scss';
                if (!isset($processedFiles[$bootstrapPath])) {
                    $processedFiles[$bootstrapPath] = true;
                    $resolvedFiles->add(new File(
                        $bootstrapPath,
                        ['vendor' => __DIR__ . '/../Resources/app/storefront/vendor']
                    ));
                }
                continue;
            }

            // Handle other @ThemeName references
            $name = mb_substr($filepath, 1);
            $configuration = $configurationCollection->getByTechnicalName($name);
            if (!$configuration) {
                throw ThemeException::couldNotFindThemeByName($name);
            }

            foreach ($this->resolve(
                $fileType,
                $configuration,
                $configurationCollection,
                $onlySourceFiles,
                $configFileResolver,
                $nextIncluded,
                $processedFiles,
                $nextProcessedConfigs
            ) as $item) {
                $itemPath = $item->getFilepath();
                if (!isset($processedFiles[$itemPath])) {
                    $processedFiles[$itemPath] = true;
                    $resolvedFiles->add($item);
                }
            }
        }

        return $resolvedFiles;
    }

    private function resolveComponentSingleFile($filepath, $fileType): ?File
    {
        $processedFilepath = $filepath;
        $componentBundleNamespace = null;
        $resolvedFile = null;

        // Extract bundle for specific bundle namespace reference like "@Components:BundleName/path"
        if (str_starts_with($filepath, '@Components:')) {
            $colonPos = strpos($filepath, ':');
            if ($colonPos !== false) {
                $slashPos = strpos($filepath, '/', $colonPos);
                if ($slashPos !== false) {
                    $componentBundleNamespace = substr($filepath, $colonPos + 1, $slashPos - $colonPos - 1);
                    // Convert to @Components/path format for parseBundleRelativePath
                    $processedFilepath = '@Components' . substr($filepath, $slashPos);
                }
            }
        }

        $relative = $this->parseBundleRelativePath($processedFilepath);
        $requestedRelativePath = $relative['path'] ?? null;

        if ($requestedRelativePath !== null) {
            foreach ($this->twigComponentHelper->getComponents() as $component) {
                // If bundle namespace is specified, filter by it
                if ($componentBundleNamespace !== null && $component->getNamespace() !== $componentBundleNamespace) {
                    continue;
                }

                $componentFilePath = null;

                // Get the appropriate path based on file type
                if ($fileType === self::SCRIPT_FILES) {
                    $componentFilePath = $component->getScriptPath();
                } elseif ($fileType === self::STYLE_FILES) {
                    $componentFilePath = $component->getStylePath();
                }

                $bundleRelativeComponentPath = $component->getNamespace() . '/Resources/views/components/' . $requestedRelativePath;

                if ($componentFilePath !== null && str_ends_with($componentFilePath, $bundleRelativeComponentPath)) {
                    $namespaceDir = $component->getRelativeNamespaceDirectory();
                    $assetName = $namespaceDir !== '' ? $namespaceDir : null;
                    $resolvedFile = new File($componentFilePath, [], $assetName);
                    break;
                }
            }
        }

        return $resolvedFile;
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

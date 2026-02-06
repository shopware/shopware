<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Twig\Components\TwigComponentHelper;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
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
            $this->collectConfigurationScriptFiles(...)
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
            fn (StorefrontPluginConfiguration $configuration) => $configuration->getStyleFiles()
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
            fn (StorefrontPluginConfiguration $configuration) => $configuration->getBaseStyleFiles()
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
     * @param array<int, string> $included List of already included files to prevent duplicates
     *
     * @return FileCollection Collection of resolved files
     */
    private function resolve(
        string $fileType,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles,
        callable $configFileResolver,
        array $included = []
    ): FileCollection {
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

        // First pass: collect all namespaced imports (@) to track what needs to be included
        foreach ($files as $file) {
            $filepath = $file->getFilepath();
            if ($this->isInclude($filepath)) {
                $nextIncluded[] = $filepath;
            }
        }

        // Second pass: process each file
        foreach ($files as $file) {
            $filepath = $file->getFilepath();

            // Handle direct file paths (not starting with @)
            if (!$this->isInclude($filepath)) {
                if (\is_file($filepath)) {
                    $resolvedFiles->add($file);

                    continue;
                }
                // removes file with old js structure (before async changes) from collection
                if (!str_ends_with($filepath, $file->assetName . '/' . basename($filepath))) {
                    continue;
                }

                throw new ThemeCompileException(
                    $themeConfig->getTechnicalName(),
                    \sprintf('Unable to load file "Resources/%s". Did you forget to build the theme? Try running ./bin/build-storefront.sh', $filepath)
                );
            }

            // Skip if this namespace was already included to prevent duplicates
            if (\in_array($filepath, $included, true)) {
                continue;
            }
            $included[] = $filepath;

            // Handle @Plugins namespace - include all non-theme plugins
            if ($filepath === '@Plugins') {
                foreach ($configurationCollection->getNoneThemes() as $plugin) {
                    foreach ($this->resolve(
                        $fileType,
                        $plugin,
                        $configurationCollection,
                        $onlySourceFiles,
                        $configFileResolver,
                        $nextIncluded
                    ) as $item) {
                        $resolvedFiles->add($item);
                    }
                }
                continue;
            }

            // Handle @Components namespace - include all Twig UX components
            if ($filepath === '@Components') {
                if (!Feature::isActive('STOREFRONT_COMPONENTS')) {
                    continue;
                }

                foreach ($this->twigComponentHelper->getComponents() as $component) {
                    $componentPath = $fileType === self::SCRIPT_FILES
                        ? $component->getScriptPath()
                        : $component->getStylePath();

                    if ($componentPath !== null) {
                        $namespaceDir = $component->getRelativeNamespaceDirectory();
                        // Use null for assetName if the namespace directory is empty to avoid double slashes
                        $assetName = $namespaceDir !== '' ? $namespaceDir : null;
                        $resolvedFiles->add(new File($componentPath, [], $assetName));
                    }
                }

                continue;
            }

            // Handle @StorefrontBootstrap namespace - include base SCSS file
            if ($filepath === '@StorefrontBootstrap') {
                // Determine which theme directory to use based on the theme being compiled
                $technicalName = $themeConfig->getTechnicalName();
                $themeDirectory = str_starts_with($technicalName, 'StorefrontExperience') ? 'scss-experience' : 'scss-default';
                
                $resolvedFiles->add(new File(
                    __DIR__ . '/../Resources/app/storefront/src/' . $themeDirectory . '/base.scss',
                    ['vendor' => __DIR__ . '/../Resources/app/storefront/vendor']
                ));

                continue;
            }

            // Handle other @ namespaces - resolve to specific theme/plugin
            $name = mb_substr($filepath, 1);
            $configuration = $configurationCollection->getByTechnicalName($name);
            if (!$configuration) {
                throw ThemeException::couldNotFindThemeByName($name);
            }
            foreach ($this->resolve($fileType, $configuration, $configurationCollection, $onlySourceFiles, $configFileResolver, $nextIncluded) as $item) {
                $resolvedFiles->add($item);
            }
        }

        return $resolvedFiles;
    }

    private function isInclude(string $file): bool
    {
        return str_starts_with($file, '@');
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

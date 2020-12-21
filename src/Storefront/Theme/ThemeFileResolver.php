<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Storefront\Theme\Exception\InvalidThemeException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\File;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

class ThemeFileResolver
{
    public const SCRIPT_FILES = 'script';
    public const STYLE_FILES = 'style';

    /**
     * @var ThemeFileImporterInterface|null will be required in v6.3.0
     */
    private $themeFileImporter;

    public function __construct(?ThemeFileImporterInterface $themeFileImporter = null)
    {
        $this->themeFileImporter = $themeFileImporter;
    }

    public function resolveFiles(
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles
    ): array {
        return [
            self::SCRIPT_FILES => $this->resolve(
                $themeConfig,
                $configurationCollection,
                $onlySourceFiles,
                function (StorefrontPluginConfiguration $configuration, bool $onlySourceFiles) {
                    $fileCollection = new FileCollection();
                    $scriptFiles = $configuration->getScriptFiles();
                    $addSourceFile = $configuration->getStorefrontEntryFilepath() && $onlySourceFiles;
                    // add source file at the beginning if no other theme is included first
                    if ($addSourceFile && ($scriptFiles->count() === 0 || !$this->isInclude($scriptFiles->first()->getFilepath()))) {
                        $fileCollection->add(new File($configuration->getStorefrontEntryFilepath()));
                    }
                    foreach ($scriptFiles as $scriptFile) {
                        if (!$this->isInclude($scriptFile->getFilepath()) && $onlySourceFiles) {
                            continue;
                        }
                        $fileCollection->add($scriptFile);
                    }
                    if ($addSourceFile && $scriptFiles->count() > 0 && $this->isInclude($scriptFiles->first()->getFilepath())) {
                        $fileCollection->add(new File($configuration->getStorefrontEntryFilepath()));
                    }

                    return $fileCollection;
                }
            ),
            self::STYLE_FILES => $this->resolve(
                $themeConfig,
                $configurationCollection,
                $onlySourceFiles,
                function (StorefrontPluginConfiguration $configuration) {
                    return $configuration->getStyleFiles();
                }
            ),
        ];
    }

    private function resolve(
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $onlySourceFiles,
        callable $configFileResolver,
        array $included = []
    ): FileCollection {
        /** @var FileCollection $files */
        $files = $configFileResolver($themeConfig, $onlySourceFiles);
        if ($files->count() === 0) {
            return $files;
        }
        $resolvedFiles = new FileCollection();
        $nextIncluded = $included;
        foreach ($files as $file) {
            $filepath = $file->getFilepath();
            if ($this->isInclude($filepath)) {
                $nextIncluded[] = $filepath;
            }
        }
        foreach ($files as $file) {
            $filepath = $file->getFilepath();
            if (!$this->isInclude($filepath)) {
                if ($this->fileExists($filepath)) {
                    $resolvedFiles->add($file);

                    continue;
                }

                throw new ThemeCompileException(
                    $themeConfig->getTechnicalName(),
                    sprintf('Unable to load file "%s". Did you forget to build the theme? Try running ./psh.phar storefront:build ', $filepath)
                );
            }

            // bundle or wildcard already included? skip to prevent duplicate style/script injection
            if (\in_array($filepath, $included, true)) {
                continue;
            }
            $included[] = $filepath;
            if ($filepath === '@Plugins') {
                foreach ($configurationCollection->getNoneThemes() as $plugin) {
                    foreach ($this->resolve($plugin, $configurationCollection, $onlySourceFiles, $configFileResolver, $nextIncluded) as $item) {
                        $resolvedFiles->add($item);
                    }
                }

                continue;
            }
            if ($filepath === '@StorefrontBootstrap') {
                $resolvedFiles->add(new File(
                    __DIR__ . '/../Resources/app/storefront/src/scss/base.scss',
                    ['vendor' => __DIR__ . '/../Resources/app/storefront/vendor']
                ));

                continue;
            }
            // Resolve @ dependencies
            $name = mb_substr($filepath, 1);
            $configuration = $configurationCollection->getByTechnicalName($name);
            if (!$configuration) {
                throw new InvalidThemeException($name);
            }
            foreach ($this->resolve($configuration, $configurationCollection, $onlySourceFiles, $configFileResolver, $nextIncluded) as $item) {
                $resolvedFiles->add($item);
            }
        }

        return $resolvedFiles;
    }

    private function isInclude(string $file): bool
    {
        return mb_strpos($file, '@') === 0;
    }

    private function fileExists(string $filepath): bool
    {
        if (!$this->themeFileImporter) {
            return file_exists($filepath);
        }

        return $this->themeFileImporter->fileExists($filepath);
    }
}

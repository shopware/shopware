<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use League\Flysystem\FilesystemInterface;
use Padaliyajay\PHPAutoprefixer\Autoprefixer;
use ScssPhp\ScssPhp\Compiler;
use ScssPhp\ScssPhp\Formatter\Crunched;
use ScssPhp\ScssPhp\Formatter\Expanded;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedScriptsEvent;
use Shopware\Storefront\Event\ThemeCompilerConcatenatedStylesEvent;
use Shopware\Storefront\Event\ThemeCompilerEnrichScssVariablesEvent;
use Shopware\Storefront\Theme\Exception\InvalidThemeException;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\FileCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Component\Asset\Package;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ThemeCompiler implements ThemeCompilerInterface
{
    /**
     * @var FilesystemInterface
     */
    private $filesystem;

    /**
     * @var Compiler
     */
    private $scssCompiler;

    /**
     * @var ThemeFileResolver
     */
    private $themeFileResolver;

    /**
     * @var ThemeFileImporterInterface
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
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var Package[]
     */
    private $packages;

    private CacheInvalidator $logger;

    public function __construct(
        FilesystemInterface $filesystem,
        FilesystemInterface $tempFilesystem,
        ThemeFileResolver $themeFileResolver,
        bool $debug,
        EventDispatcherInterface $eventDispatcher,
        ThemeFileImporterInterface $themeFileImporter,
        EntityRepositoryInterface $mediaRepository,
        iterable $packages,
        CacheInvalidator $logger
    ) {
        $this->filesystem = $filesystem;
        $this->tempFilesystem = $tempFilesystem;
        $this->themeFileResolver = $themeFileResolver;
        $this->themeFileImporter = $themeFileImporter;

        $this->scssCompiler = new Compiler();
        $this->scssCompiler->setImportPaths('');

        $this->scssCompiler->setFormatter($debug ? Expanded::class : Crunched::class);
        $this->eventDispatcher = $eventDispatcher;
        $this->mediaRepository = $mediaRepository;
        $this->packages = $packages;
        $this->logger = $logger;
    }

    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $withAssets = true
    ): void {
        $themePrefix = self::getThemePrefix($salesChannelId, $themeId);
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

        try {
            $cssOutput = $this->scssCompiler->compile($variables . $concatenatedStyles);
        } catch (\Throwable $exception) {
            throw new ThemeCompileException(
                $configuration->getTechnicalName(),
                $exception->getMessage()
            );
        }
        $autoPreFixer = new Autoprefixer($cssOutput);
        /** @var string|false $compiled */
        $compiled = $autoPreFixer->compile();
        if ($compiled === false) {
            throw new ThemeCompileException(
                $configuration->getTechnicalName(),
                'CSS parser not initialized'
            );
        }

        return $compiled;
    }

    private function formatVariables(array $variables): array
    {
        return array_map(function ($value, $key) {
            return sprintf('$%s: %s;', $key, $value);
        }, $variables, array_keys($variables));
    }

    private function dumpVariables(array $config, string $salesChannelId): string
    {
        if (!\array_key_exists('fields', $config)) {
            return '';
        }

        $variables = [];
        $mediaIds = [];
        foreach ($config['fields'] as $key => $data) {
            if (!isset($data['value'])) {
                continue;
            }

            // Do not include fields which have the scss option set to false
            if (\array_key_exists('scss', $data) && $data['scss'] === false) {
                continue;
            }

            // value must not be an empty string since because an empty value can not be compiled
            if ($data['value'] === '') {
                continue;
            }

            // if no type is set just use the value and continue
            if (!isset($data['type'])) {
                continue;
            }

            if (\in_array($data['type'], ['media', 'textarea'], true)) {
                if ($data['type'] === 'media') {
                    // Add id of media which needs to be resolved
                    if (Uuid::isValid($data['value'])) {
                        $mediaIds[$key] = $data['value'];
                    }
                }

                $variables[$key] = '\'' . $data['value'] . '\'';
            } elseif ($data['type'] === 'switch' || $data['type'] === 'checkbox') {
                $variables[$key] = (int) ($data['value']);
            } else {
                $variables[$key] = $data['value'];
            }
        }

        // Resolve media urls
        if (\count($mediaIds) > 0) {
            /** @var MediaCollection $medias */
            $medias = $this->mediaRepository
                ->search(
                    new Criteria(array_values($mediaIds)),
                    Context::createDefaultContext()
                )
                ->getEntities();

            foreach ($mediaIds as $key => $mediaId) {
                $media = $medias->get($mediaId);
                if ($media === null) {
                    unset($variables[$key]);

                    continue;
                }

                $variables[$key] = '\'' . $media->getUrl() . '\'';
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

    private function getVariableDumpTemplate(): string
    {
        return <<<PHP_EOL
// ATTENTION! This file is auto generated by the #class# and should not be edited.

#variables#

PHP_EOL;
    }
}

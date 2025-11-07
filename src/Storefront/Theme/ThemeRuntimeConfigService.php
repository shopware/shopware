<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

/**
 * @internal
 */
#[Package('framework')]
class ThemeRuntimeConfigService
{
    /**
     * @var array<string, ThemeRuntimeConfig|null>
     */
    private array $runtimeConfigCacheById = [];

    /**
     * @var array<string, ThemeRuntimeConfig|null>
     */
    private array $runtimeConfigCacheByName = [];

    /**
     * @var string[]|null
     */
    private ?array $activeThemeNamesCache = null;

    public function __construct(
        private readonly ThemeFileResolver $themeFileResolver,
        private readonly StorefrontPluginRegistry $pluginRegistry,
        private readonly ThemeMergedConfigBuilder $mergedConfigBuilder,
        private readonly ThemeRuntimeConfigStorage $storage,
    ) {
    }

    public function getResolvedRuntimeConfig(string $themeId): ?ThemeRuntimeConfig
    {
        $config = $this->getRuntimeConfig($themeId);

        if ($config === null) {
            return null;
        }

        if ($config->scriptFiles === null) {
            $config = $this->generateRuntimeConfigById($themeId, true);
        }

        return $config;
    }

    public function getRuntimeConfigByName(string $technicalName): ?ThemeRuntimeConfig
    {
        if (\array_key_exists($technicalName, $this->runtimeConfigCacheByName)) {
            return $this->runtimeConfigCacheByName[$technicalName];
        }

        $config = $this->storage->getByName($technicalName);

        if ($config === null) {
            $config = $this->generateRuntimeConfigByName($technicalName);
        }

        $this->runtimeConfigCacheByName[$technicalName] = $config;
        if ($config !== null) {
            $this->runtimeConfigCacheById[$config->themeId] = $config;
        }

        return $config;
    }

    public function getRuntimeConfig(string $themeId): ?ThemeRuntimeConfig
    {
        if (\array_key_exists($themeId, $this->runtimeConfigCacheById)) {
            return $this->runtimeConfigCacheById[$themeId];
        }

        $config = $this->storage->getById($themeId);

        if ($config === null) {
            $config = $this->generateRuntimeConfigById($themeId);
        }

        $this->runtimeConfigCacheById[$themeId] = $config;
        if ($config !== null) {
            $this->runtimeConfigCacheByName[$config->technicalName] = $config;
        }

        return $config;
    }

    /**
     * Refreshes the whole ThemeRuntimeConfig object.
     */
    public function refreshRuntimeConfig(
        string $themeId,
        StorefrontPluginConfiguration $themeConfig,
        Context $context,
        bool $failOnFileResolveError = false,
        ?StorefrontPluginConfigurationCollection $configCollection = null
    ): ThemeRuntimeConfig {
        if ($configCollection === null) {
            $configCollection = $this->pluginRegistry->getConfigurations();
        }

        $scriptFiles = null;
        try {
            // will throw an exception if theme was not built yet
            $scriptFiles = $this->themeFileResolver->resolveScriptFiles($themeConfig, $configCollection, false)->getPublicPaths('js');
        } catch (ThemeCompileException|AppException $e) {
            if ($failOnFileResolveError) {
                throw $e;
            }
        }

        $runtimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => $themeId,
            'technicalName' => $themeConfig->getTechnicalName(),
            'resolvedConfig' => $this->mergedConfigBuilder->getPlainThemeConfiguration($themeId, $context),
            'viewInheritance' => $themeConfig->getViewInheritance(),
            'scriptFiles' => $scriptFiles,
            'iconSets' => $this->prepareIconSets($themeConfig),
            'updatedAt' => new \DateTime(),
        ]);

        $this->storage->save($runtimeConfig);
        $this->cacheConfig($runtimeConfig);

        // Handle theme copies
        $copyIds = $this->storage->getCopiesIds($themeId);
        foreach ($copyIds as $copyId) {
            $copyConfig = $runtimeConfig->with([
                'themeId' => $copyId,
                'technicalName' => null,
                'resolvedConfig' => $this->mergedConfigBuilder->getPlainThemeConfiguration($copyId, $context),
                'updatedAt' => new \DateTime(),
            ]);

            $this->storage->save($copyConfig);
            $this->cacheConfig($copyConfig);
        }

        return $runtimeConfig;
    }

    /**
     * Updates theme configuration values in the runtime config.
     */
    public function refreshConfigValues(string $themeId, Context $context): void
    {
        $this->updateThemeConfigValues($themeId, $context);

        // Get all child themes and update their configs
        $childThemeIds = $this->storage->getChildThemeIds($themeId);
        foreach ($childThemeIds as $childThemeId) {
            $this->updateThemeConfigValues($childThemeId, $context);
        }
    }

    public function resetCaches(): void
    {
        $this->runtimeConfigCacheById = [];
        $this->runtimeConfigCacheByName = [];
        $this->activeThemeNamesCache = null;
    }

    /**
     * @return array<string>
     */
    public function getActiveThemeNames(): array
    {
        if ($this->activeThemeNamesCache !== null) {
            return $this->activeThemeNamesCache;
        }

        $this->activeThemeNamesCache = $this->storage->getActiveThemeNames();

        return $this->activeThemeNamesCache;
    }

    private function updateThemeConfigValues(string $themeId, Context $context): void
    {
        $runtimeConfig = $this->getRuntimeConfig($themeId);
        if ($runtimeConfig === null) {
            return;
        }

        $mergedConfig = $this->mergedConfigBuilder->getPlainThemeConfiguration($themeId, $context);
        $updatedRuntimeConfig = $runtimeConfig->with([
            'resolvedConfig' => $mergedConfig,
            'updatedAt' => new \DateTime(),
        ]);

        $this->storage->save($updatedRuntimeConfig);
        $this->cacheConfig($updatedRuntimeConfig);
    }

    private function cacheConfig(ThemeRuntimeConfig $config): void
    {
        $this->runtimeConfigCacheById[$config->themeId] = $config;
        $this->runtimeConfigCacheByName[$config->technicalName] = $config;
    }

    /**
     * @return array<string, array{path: string, namespace: string}>
     */
    private function prepareIconSets(StorefrontPluginConfiguration $themeConfig): array
    {
        $iconConfig = [];
        foreach ($themeConfig->getIconSets() as $pack => $path) {
            $iconConfig[$pack] = [
                'path' => $path,
                'namespace' => $themeConfig->getTechnicalName(),
            ];
        }

        return $iconConfig;
    }

    private function generateRuntimeConfigById(string $themeId, bool $failOnFileResolve = false): ?ThemeRuntimeConfig
    {
        $technicalName = $this->storage->getThemeTechnicalName($themeId);
        if ($technicalName === null) {
            return null;
        }

        $configCollection = $this->pluginRegistry->getConfigurations();
        $themeConfig = $configCollection->getByTechnicalName($technicalName);

        if ($themeConfig === null) {
            return null;
        }

        return $this->refreshRuntimeConfig($themeId, $themeConfig, Context::createDefaultContext(), $failOnFileResolve, $configCollection);
    }

    private function generateRuntimeConfigByName(string $technicalName): ?ThemeRuntimeConfig
    {
        $configCollection = $this->pluginRegistry->getConfigurations();
        $themeConfig = $configCollection->getByTechnicalName($technicalName);

        if ($themeConfig === null) {
            return null;
        }

        $themeId = $this->storage->getThemeIdByTechnicalName($technicalName);
        if ($themeId === null) {
            return null;
        }

        return $this->refreshRuntimeConfig($themeId, $themeConfig, Context::createDefaultContext(), false, $configCollection);
    }
}

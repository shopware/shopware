<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\ThemeCompileException;
use Shopware\Storefront\Theme\Exception\ThemeException;
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
        $runtimeConfig = $this->getRuntimeConfig($themeId);

        if ($runtimeConfig === null) {
            return null;
        }

        if ($runtimeConfig->scriptFiles === null) {
            // now we need to regenerate config
            $configCollection = $this->pluginRegistry->getConfigurations();
            $themeConfig = $configCollection->getByTechnicalName($runtimeConfig->technicalName);

            if ($themeConfig === null) {
                throw ThemeException::errorLoadingFromPluginRegistry($runtimeConfig->technicalName);
            }

            $runtimeConfig = $this->refreshRuntimeConfig($runtimeConfig->themeId, $themeConfig, Context::createDefaultContext(), true);
        }

        return $runtimeConfig;
    }

    public function getRuntimeConfigByName(string $technicalName): ?ThemeRuntimeConfig
    {
        if (\array_key_exists($technicalName, $this->runtimeConfigCacheByName)) {
            return $this->runtimeConfigCacheByName[$technicalName];
        }

        $config = $this->storage->getByName($technicalName);

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

        $this->runtimeConfigCacheById[$themeId] = $config;
        if ($config !== null) {
            $this->runtimeConfigCacheByName[$config->technicalName] = $config;
        }

        return $config;
    }

    /**
     * Refreshes the whole ThemeRuntimeConfig object.
     */
    public function refreshRuntimeConfig(string $themeId, StorefrontPluginConfiguration $themeConfig, Context $context, bool $filesRequired = false, ?StorefrontPluginConfigurationCollection $configCollection = null): ThemeRuntimeConfig
    {
        if ($configCollection === null) {
            $configCollection = $this->pluginRegistry->getConfigurations();
        }

        $scriptFiles = null;
        try {
            // will throw an exception if theme was not built yet
            $scriptFiles = $this->resolveJs($themeConfig, $configCollection);
        } catch (ThemeCompileException $e) {
            $filesRequired && throw $e;
        }

        $runtimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => $themeId,
            'technicalName' => $themeConfig->getTechnicalName(),
            'resolvedConfig' => $this->mergedConfigBuilder->getThemeConfiguration($themeId, false, $context),
            'viewInheritance' => $themeConfig->getViewInheritance(),
            'scriptFiles' => $scriptFiles,
            'iconSets' => $this->prepareIconSets($themeConfig),
            'updatedAt' => new \DateTime(),
        ]);

        $this->storage->save($runtimeConfig);

        // Cache the new configuration
        $this->cacheConfig($runtimeConfig);

        return $runtimeConfig;
    }

    /**
     * Updates theme configuration values in the runtime config.
     */
    public function refreshConfigValues(string $themeId, Context $context): void
    {
        $runtimeConfig = $this->getRuntimeConfig($themeId);
        if ($runtimeConfig === null) {
            return;
        }

        $mergedConfig = $this->mergedConfigBuilder->getThemeConfiguration($themeId, false, $context);
        $updatedRuntimeConfig = $runtimeConfig->with([
            'resolvedConfig' => $mergedConfig,
            'updatedAt' => new \DateTime(),
        ]);

        // Update and cache the updated configuration
        $this->storage->save($updatedRuntimeConfig);
        $this->cacheConfig($updatedRuntimeConfig);
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

    /**
     * @return array<string>
     */
    private function resolveJs(StorefrontPluginConfiguration $themeConfig, StorefrontPluginConfigurationCollection $configCollection): array
    {
        $resolvedFiles = $this->themeFileResolver->resolveFiles($themeConfig, $configCollection, false);

        return $resolvedFiles[ThemeFileResolver::SCRIPT_FILES]->getPublicPaths('js');
    }
}

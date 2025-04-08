<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
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
        private readonly ThemeService $themeService,
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
            $runtimeConfig = $this->refreshRuntimeConfig($runtimeConfig->themeId, $runtimeConfig->technicalName, Context::createDefaultContext(), true);
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

    public function refreshRuntimeConfig(string $themeId, string $themeTechnicalName, Context $context, bool $resolveFiles, ?StorefrontPluginConfigurationCollection $configCollection = null): ThemeRuntimeConfig
    {
        if ($configCollection === null) {
            $configCollection = $this->pluginRegistry->getConfigurations();
        }
        $themeConfig = $configCollection->getByTechnicalName($themeTechnicalName);

        if ($themeConfig === null) {
            throw ThemeException::errorLoadingFromPluginRegistry($themeTechnicalName);
        }

        $runtimeConfig = ThemeRuntimeConfig::fromArray([
            'themeId' => $themeId,
            'technicalName' => $themeTechnicalName,
            'resolvedConfig' => $this->themeService->getThemeConfiguration($themeId, false, $context),
            'viewInheritance' => $themeConfig->getViewInheritance(),
            'scriptFiles' => $resolveFiles ? $this->resolveJs($themeConfig, $configCollection) : null,
            'iconSets' => $this->prepareIconSets($themeConfig),
            'updatedAt' => new \DateTime(),
        ]);

        $this->storage->save($runtimeConfig);

        // Cache the new configuration
        $this->cacheConfig($runtimeConfig);

        return $runtimeConfig;
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

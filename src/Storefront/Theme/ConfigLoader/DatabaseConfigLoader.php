<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\ConfigLoader;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Exception\InvalidThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Shopware\Storefront\Theme\ThemeConfigField;
use Shopware\Storefront\Theme\ThemeEntity;

class DatabaseConfigLoader extends AbstractConfigLoader
{
    private EntityRepositoryInterface $themeRepository;

    private StorefrontPluginRegistryInterface $extensionRegistry;

    private EntityRepositoryInterface $mediaRepository;

    private string $baseTheme;

    public function __construct(
        EntityRepositoryInterface $themeRepository,
        StorefrontPluginRegistryInterface $extensionRegistry,
        EntityRepositoryInterface $mediaRepository,
        string $baseTheme = StorefrontPluginRegistry::BASE_THEME_NAME
    ) {
        $this->themeRepository = $themeRepository;
        $this->extensionRegistry = $extensionRegistry;
        $this->mediaRepository = $mediaRepository;
        $this->baseTheme = $baseTheme;
    }

    public function getDecorated(): AbstractConfigLoader
    {
        throw new DecorationPatternException(self::class);
    }

    public function load(string $themeId, Context $context): StorefrontPluginConfiguration
    {
        $pluginConfig = $this->loadConfigByName($themeId, $context);

        if (!$pluginConfig) {
            throw new InvalidThemeException($themeId);
        }

        $pluginConfig = clone $pluginConfig;

        $config = $this->loadCompileConfig($themeId, $context);

        $pluginConfig->setThemeConfig($config);

        $this->resolveMediaIds($pluginConfig, $context);

        return $pluginConfig;
    }

    private function loadCompileConfig(string $themeId, Context $context): array
    {
        $config = $this->loadRecursiveConfig($themeId, $context);

        $field = new ThemeConfigField();

        foreach ($config['fields'] as $name => $item) {
            $clone = clone $field;
            $clone->setName($name);
            $clone->assign($item);
            $config[$name] = $clone;
        }

        return json_decode((string) json_encode($config), true);
    }

    private function loadRecursiveConfig(string $themeId, Context $context, bool $withBase = true): array
    {
        $criteria = new Criteria();
        $criteria->setTitle('theme-service::load-config');

        $themes = $this->themeRepository->search($criteria, $context);

        $theme = $themes->get($themeId);

        /** @var ThemeEntity|null $theme */
        if (!$theme) {
            throw new InvalidThemeException($themeId);
        }
        $baseThemeConfig = [];

        if ($withBase) {
            /** @var ThemeEntity $baseTheme */
            $baseTheme = $themes->filter(function (ThemeEntity $themeEntry) {
                return $themeEntry->getTechnicalName() === $this->baseTheme;
            })->first();

            $baseThemeConfig = $this->mergeStaticConfig($baseTheme);
        }

        if ($theme->getParentThemeId()) {
            $parentThemes = $this->getParentThemeIds($themes, $theme);

            foreach ($parentThemes as $parentTheme) {
                $configuredParentTheme = $this->mergeStaticConfig($parentTheme);
                $baseThemeConfig = array_replace_recursive($baseThemeConfig, $configuredParentTheme);
            }
        }

        $configuredTheme = $this->mergeStaticConfig($theme);

        return array_replace_recursive($baseThemeConfig, $configuredTheme);
    }

    private function getParentThemeIds(EntitySearchResult $themes, ThemeEntity $mainTheme, array $parentThemes = []): array
    {
        // add configured parent themes
        foreach ($this->getConfigInheritance($mainTheme) as $parentThemeName) {
            $parentTheme = $themes->filter(function (ThemeEntity $themeEntry) use ($parentThemeName) {
                return $themeEntry->getTechnicalName() === str_replace('@', '', $parentThemeName);
            })->first();

            if (!($parentTheme instanceof ThemeEntity)) {
                continue;
            }

            if (\array_key_exists($parentTheme->getId(), $parentThemes)) {
                continue;
            }

            $parentThemes[$parentTheme->getId()] = $parentTheme;
            if ($parentTheme->getParentThemeId()) {
                $parentThemes = $this->getParentThemeIds($themes, $mainTheme, $parentThemes);
            }
        }

        if ($mainTheme->getParentThemeId() === null) {
            return $parentThemes;
        }

        // add database defined parent theme
        $parentTheme = $themes->filter(function (ThemeEntity $themeEntry) use ($mainTheme) {
            return $themeEntry->getId() === $mainTheme->getParentThemeId();
        })->first();

        if (!($parentTheme instanceof ThemeEntity)) {
            return $parentThemes;
        }

        if (\array_key_exists($parentTheme->getId(), $parentThemes)) {
            return $parentThemes;
        }

        $parentThemes[$parentTheme->getId()] = $parentTheme;
        if ($parentTheme->getParentThemeId()) {
            $parentThemes = $this->getParentThemeIds($themes, $mainTheme, $parentThemes);
        }

        return $parentThemes;
    }

    private function loadConfigByName(string $themeId, Context $context): ?StorefrontPluginConfiguration
    {
        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository
            ->search(new Criteria([$themeId]), $context)
            ->get($themeId);

        if ($theme === null) {
            return $this->extensionRegistry
                ->getConfigurations()
                ->getByTechnicalName($this->baseTheme);
        }

        $pluginConfig = null;
        if ($theme->getTechnicalName() !== null) {
            $pluginConfig = $this->extensionRegistry
                ->getConfigurations()
                ->getByTechnicalName($theme->getTechnicalName());
        }

        if ($pluginConfig !== null) {
            return $pluginConfig;
        }

        if ($theme->getParentThemeId() !== null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('id', $theme->getParentThemeId()));

            /** @var ThemeEntity $parentTheme */
            $parentTheme = $this->themeRepository
                ->search($criteria, $context)
                ->first();

            if (!\is_string($parentTheme->getTechnicalName())) {
                return $this->extensionRegistry
                    ->getConfigurations()
                    ->getByTechnicalName($this->baseTheme);
            }

            return $this->extensionRegistry
                ->getConfigurations()
                ->getByTechnicalName($parentTheme->getTechnicalName());
        }

        return $this->extensionRegistry
            ->getConfigurations()
            ->getByTechnicalName($this->baseTheme);
    }

    private function mergeStaticConfig(ThemeEntity $theme): array
    {
        $configuredTheme = [];

        $pluginConfig = null;
        if ($theme->getTechnicalName()) {
            $pluginConfig = $this->extensionRegistry->getConfigurations()->getByTechnicalName($theme->getTechnicalName());
        }

        if ($pluginConfig !== null) {
            $configuredTheme = $pluginConfig->getThemeConfig() ?? [];
        }

        if ($theme->getBaseConfig() !== null) {
            $configuredTheme = array_replace_recursive($configuredTheme, $theme->getBaseConfig());
        }

        if ($theme->getConfigValues() === null) {
            return $configuredTheme;
        }

        foreach ($theme->getConfigValues() as $fieldName => $configValue) {
            if (isset($configValue['value'])) {
                $configuredTheme['fields'][$fieldName]['value'] = $configValue['value'];
            }
        }

        return $configuredTheme;
    }

    private function resolveMediaIds(StorefrontPluginConfiguration $pluginConfig, Context $context): void
    {
        $config = $pluginConfig->getThemeConfig();

        if (!\is_array($config)) {
            return;
        }

        $ids = [];

        // Collect all ids
        foreach ($config['fields'] as $_ => $data) {
            if (!isset($data['value'])
                || $data['value'] === ''
                || !\is_string($data['value'])
                || (\array_key_exists('scss', $data) && $data['scss'] === false)
                || (isset($data['type']) && $data['type'] !== 'media')
                || !Uuid::isValid($data['value'])
            ) {
                continue;
            }

            $ids[] = $data['value'];
        }

        if (\count($ids) === 0) {
            return;
        }

        $criteria = new Criteria($ids);

        /** @var MediaCollection $mediaResult */
        $mediaResult = $this->mediaRepository->search($criteria, $context)->getEntities();

        // Replace all ids with the actual url
        foreach ($config['fields'] as $key => $data) {
            if (!isset($data['value']) || !\is_string($data['value'])) {
                continue;
            }

            if (
                $data['value'] === ''
                || (\array_key_exists('scss', $data) && $data['scss'] === false)
                || (isset($data['type']) && $data['type'] !== 'media')
                || !Uuid::isValid($data['value'])
                || !$mediaResult->has($data['value'])
            ) {
                continue;
            }

            $media = $mediaResult->get($data['value']);

            if ($media !== null) {
                $config['fields'][$key]['value'] = $media->getUrl();

                if (!Feature::isActive('FEATURE_NEXT_19048')) {
                    $config[$key]['value'] = $media->getUrl();
                }
            }
        }

        $pluginConfig->setThemeConfig($config);
    }

    private function getConfigInheritance(ThemeEntity $mainTheme): array
    {
        if (!\is_array($mainTheme->getBaseConfig())) {
            return [];
        }

        if (!\array_key_exists('configInheritance', $mainTheme->getBaseConfig())) {
            return [];
        }

        if (!\is_array($mainTheme->getBaseConfig()['configInheritance'])) {
            return [];
        }

        if (empty($mainTheme->getBaseConfig()['configInheritance'])) {
            return [];
        }

        return $mainTheme->getBaseConfig()['configInheritance'];
    }
}

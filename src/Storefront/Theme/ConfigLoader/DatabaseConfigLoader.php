<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\ConfigLoader;

use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
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

    public function __construct(
        EntityRepositoryInterface $themeRepository,
        StorefrontPluginRegistryInterface $extensionRegistry,
        EntityRepositoryInterface $mediaRepository
    ) {
        $this->themeRepository = $themeRepository;
        $this->extensionRegistry = $extensionRegistry;
        $this->mediaRepository = $mediaRepository;
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

    private function loadRecursiveConfig(string $themeId, Context $context): array
    {
        $criteria = new Criteria([$themeId]);

        $theme = $this->themeRepository
            ->search($criteria, $context)
            ->first();

        if (!$theme instanceof ThemeEntity) {
            throw new InvalidThemeException($themeId);
        }

        $config = $this->mergeStaticConfig($theme);

        $parentId = $theme->getParentThemeId();
        if ($parentId) {
            $parent = $this->loadRecursiveConfig($parentId, $context);

            return array_replace_recursive($parent, $config);
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));

        $theme = $this->themeRepository
            ->search($criteria, $context)
            ->first();

        if (!$theme instanceof ThemeEntity) {
            throw new InvalidThemeException(StorefrontPluginRegistry::BASE_THEME_NAME);
        }

        $base = $this->mergeStaticConfig($theme);

        return array_replace_recursive($base, $config);
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
                ->getByTechnicalName(StorefrontPluginRegistry::BASE_THEME_NAME);
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

            \assert(\is_string($parentTheme->getTechnicalName()));

            return $this->extensionRegistry
                ->getConfigurations()
                ->getByTechnicalName($parentTheme->getTechnicalName());
        }

        return $this->extensionRegistry
            ->getConfigurations()
            ->getByTechnicalName(StorefrontPluginRegistry::BASE_THEME_NAME);
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

        if ($theme !== null && $theme->getBaseConfig() !== null) {
            $configuredTheme = array_replace_recursive($configuredTheme, $theme->getBaseConfig());
        }

        if ($theme !== null && $theme->getConfigValues() !== null) {
            $configuredThemeFields = [];
            if (\array_key_exists('fields', $configuredTheme)) {
                $configuredThemeFields = $configuredTheme['fields'];
            }
            $configuredTheme['fields'] = array_replace_recursive($configuredThemeFields, $theme->getConfigValues());
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
            if (!isset($data['value'])) {
                continue;
            }

            if (
                $data['value'] === ''
                || (\array_key_exists('scss', $data) && $data['scss'] === false)
                || $data['type'] !== 'media'
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
            if (!isset($data['value'])) {
                continue;
            }

            if (
                $data['value'] === ''
                || (\array_key_exists('scss', $data) && $data['scss'] === false)
                || $data['type'] !== 'media'
                || !Uuid::isValid($data['value'])
                || !$mediaResult->has($data['value'])
            ) {
                continue;
            }

            $media = $mediaResult->get($data['value']);

            if ($media !== null) {
                $config[$key]['value'] = $media->getUrl();
            }
        }

        $pluginConfig->setThemeConfig($config);
    }
}

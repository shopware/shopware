<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Exception\InvalidThemeConfigException;
use Shopware\Storefront\Theme\Exception\InvalidThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Contracts\Cache\CacheInterface;

class ThemeService
{
    /**
     * @var StorefrontPluginRegistry
     */
    private $pluginRegistry;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $themeSalesChannelRepository;

    /**
     * @var EntityRepositoryInterface
     */
    private $mediaRepository;

    /**
     * @var StorefrontPluginRegistry
     */
    private $storefrontPluginRegistry;

    /**
     * @var ThemeCompiler
     */
    private $themeCompiler;

    /**
     * @var CacheInterface
     */
    private $cache;

    public function __construct(
        StorefrontPluginRegistry $pluginRegistry,
        EntityRepositoryInterface $themeRepository,
        EntityRepositoryInterface $themeSalesChannelRepository,
        EntityRepositoryInterface $mediaRepository,
        StorefrontPluginRegistry $storefrontPluginRegistry,
        ThemeCompiler $themeCompiler,
        CacheInterface $cache
    ) {
        $this->pluginRegistry = $pluginRegistry;
        $this->themeRepository = $themeRepository;
        $this->themeSalesChannelRepository = $themeSalesChannelRepository;
        $this->mediaRepository = $mediaRepository;
        $this->storefrontPluginRegistry = $storefrontPluginRegistry;
        $this->themeCompiler = $themeCompiler;
        $this->cache = $cache;
    }

    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        Context $context,
        ?StorefrontPluginConfigurationCollection $configurationCollection = null,
        bool $withAssets = true
    ): void {
        $themePluginConfiguration = $this->getPluginConfiguration(
            $salesChannelId,
            $themeId,
            false,
            $context
        );

        $this->themeCompiler->compileTheme(
            $salesChannelId,
            $themeId,
            $themePluginConfiguration,
            $configurationCollection ?? $this->storefrontPluginRegistry->getConfigurations(),
            $withAssets
        );

        // invalidate cache and warm up
        $this->cache->delete('theme.resolved-config.' . $themeId);
        $this->getResolvedThemeConfiguration($themeId, $context);
    }

    public function updateTheme(string $themeId, ?array $config, ?string $parentThemeId, Context $context): void
    {
        $criteria = new Criteria([$themeId]);
        $criteria->addAssociation('salesChannels');
        $theme = $this->themeRepository->search($criteria, $context)->get($themeId);

        if (!$theme) {
            throw new InvalidThemeException($themeId);
        }

        $data = ['id' => $themeId];
        if ($config) {
            foreach ($config as $key => $value) {
                $data['configValues'][$key] = $value;
            }
        }

        if ($parentThemeId) {
            $data['parentThemeId'] = $parentThemeId;
        }

        if (array_key_exists('configValues', $data) && $theme->getConfigValues()) {
            $data['configValues'] = array_replace_recursive($theme->getConfigValues(), $data['configValues']);
        }

        $this->themeRepository->update([$data], $context);

        foreach ($theme->getSalesChannels() as $salesChannel) {
            $this->compileTheme($salesChannel->getId(), $themeId, $context, null, false);
        }
    }

    public function assignTheme(string $themeId, string $salesChannelId, Context $context): bool
    {
        $this->compileTheme($salesChannelId, $themeId, $context);

        $this->themeSalesChannelRepository->upsert([[
            'themeId' => $themeId,
            'salesChannelId' => $salesChannelId,
        ]], $context);

        return true;
    }

    public function resetTheme(string $themeId, Context $context): void
    {
        $criteria = new Criteria([$themeId]);
        $theme = $this->themeRepository->search($criteria, $context)->get($themeId);

        if (!$theme) {
            throw new InvalidThemeException($themeId);
        }

        $data = ['id' => $themeId];
        $data['configValues'] = null;

        $this->themeRepository->update([$data], $context);
    }

    /**
     * @throws InvalidThemeConfigException
     * @throws InvalidThemeException
     * @throws InconsistentCriteriaIdsException
     */
    public function getThemeConfiguration(string $themeId, bool $translate, Context $context): array
    {
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));
        /** @var ThemeEntity $baseTheme */
        $baseTheme = $this->themeRepository->search($criteria, $context)->first();

        $baseThemeConfig = $this->mergeStaticConfig($baseTheme);

        $criteria = new Criteria([$themeId]);

        /** @var ThemeEntity|null $theme */
        $theme = $this->themeRepository->search($criteria, $context)->get($themeId);

        if (!$theme) {
            throw new InvalidThemeException($themeId);
        }

        $themeConfigFieldFactory = new ThemeConfigFieldFactory();
        $configFields = [];

        $configuredTheme = $this->mergeStaticConfig($theme);

        $themeConfig = array_replace_recursive($baseThemeConfig, $configuredTheme);

        foreach ($themeConfig['fields'] as $name => $item) {
            $configFields[$name] = $themeConfigFieldFactory->create($name, $item);
        }

        $configFields = json_decode(json_encode($configFields), true);

        $labels = array_replace_recursive($baseTheme->getLabels() ?? [], $theme->getLabels() ?? []);
        if ($translate && !empty($labels)) {
            $configFields = $this->translateLabels($configFields, $labels);
        }

        $helpTexts = array_replace_recursive($baseTheme->getHelpTexts() ?? [], $theme->getHelpTexts() ?? []);
        if ($translate && !empty($helpTexts)) {
            $configFields = $this->translateHelpTexts($configFields, $helpTexts);
        }

        $themeConfig['fields'] = $configFields;

        return $themeConfig;
    }

    public function getResolvedThemeConfiguration(string $themeId, Context $context): array
    {
        return $this->cache->get('theme.resolved-config.' . $themeId, function () use ($themeId, $context) {
            $config = $this->getThemeConfiguration($themeId, false, $context);
            $resolvedConfig = [];
            $mediaItems = [];
            if (!array_key_exists('fields', $config)) {
                return [];
            }

            foreach ($config['fields'] as $key => $data) {
                if ($data['type'] === 'media' && $data['value'] && Uuid::isValid($data['value'])) {
                    $mediaItems[$data['value']][] = $key;
                }
                $resolvedConfig[$key] = $data['value'];
            }

            $result = $this->mediaRepository->search(new Criteria(array_keys($mediaItems)), $context);

            foreach ($result as $media) {
                if (!array_key_exists($media->getId(), $mediaItems)) {
                    continue;
                }

                foreach ($mediaItems[$media->getId()] as $key) {
                    $resolvedConfig[$key] = $media->getUrl();
                }
            }

            return $resolvedConfig;
        });
    }

    public function getThemeConfigurationFields(string $themeId, bool $translate, Context $context): array
    {
        $mergedConfig = $this->getThemeConfiguration($themeId, $translate, $context)['fields'];

        $translations = [];
        if ($translate) {
            $translations = $this->getTranslations($themeId, $context);
            $mergedConfig = $this->translateLabels($mergedConfig, $translations);
        }
        $blocks = [];
        $noblocks = [
            'label' => $this->getBlockLabel('unordered', $translations),
            'sections' => [],
        ];

        foreach ($mergedConfig as $fieldName => $fieldConfig) {
            $section = $this->getSection($fieldConfig);

            if (!isset($fieldConfig['block'])) {
                $noblocks['sections'][$section] = [
                    'label' => $this->getSectionLabel($section, $translations),
                    $fieldName => [
                        'label' => $fieldConfig['label'],
                        'helpText' => $fieldConfig['helpText'] ?? null,
                        'type' => $fieldConfig['type'],
                        'custom' => $fieldConfig['custom'],
                    ],
                ];
            } elseif (!isset($blocks[$fieldConfig['block']])) {
                $blocks[$fieldConfig['block']] = [
                    'label' => $this->getBlockLabel($fieldConfig['block'], $translations),
                    'sections' => [
                        $section => [
                            'label' => $this->getSectionLabel($section, $translations),
                            $fieldName => [
                                'label' => $fieldConfig['label'],
                                'helpText' => $fieldConfig['helpText'] ?? null,
                                'type' => $fieldConfig['type'],
                                'custom' => $fieldConfig['custom'],
                            ],
                        ],
                    ],
                ];
            } elseif (isset($blocks[$fieldConfig['block']]['sections'][$section])) {
                $blocks[$fieldConfig['block']]['sections'][$section][$fieldName] = [
                    'label' => $fieldConfig['label'],
                    'helpText' => $fieldConfig['helpText'] ?? null,
                    'type' => $fieldConfig['type'],
                    'custom' => $fieldConfig['custom'],
                ];
            } else {
                $blocks[$fieldConfig['block']]['sections'][$section] = [
                    'label' => $this->getSectionLabel($section, $translations),
                    $fieldName => [
                        'label' => $fieldConfig['label'],
                        'helpText' => $fieldConfig['helpText'] ?? null,
                        'type' => $fieldConfig['type'],
                        'custom' => $fieldConfig['custom'],
                    ],
                ];
            }
        }

        $blocks['unordered'] = $noblocks;

        return $blocks;
    }

    private function getPluginConfiguration(
        ?string $salesChannelId,
        ?string $themeId,
        bool $translate,
        Context $context
    ): StorefrontPluginConfiguration {
        if ($themeId === null) {
            $criteria = new Criteria();
            $criteria->addFilter(new EqualsFilter('salesChannel.id', $salesChannelId));
            /** @var ThemeEntity|null $theme */
            $theme = $this->themeRepository->search($criteria, $context)->first();
        } else {
            /** @var ThemeEntity|null $theme */
            $theme = $this->themeRepository->search(new Criteria([$themeId]), $context)->get($themeId);
        }

        if ($theme === null) {
            $pluginConfig = $this->pluginRegistry->getConfigurations()->getByTechnicalName(
                StorefrontPluginRegistry::BASE_THEME_NAME
            );
            if (!$pluginConfig) {
                throw new InvalidThemeException(StorefrontPluginRegistry::BASE_THEME_NAME);
            }

            return $pluginConfig;
        }
        $pluginConfig = null;
        if ($theme->getTechnicalName() !== null) {
            $pluginConfig = $this->pluginRegistry->getConfigurations()->getByTechnicalName($theme->getTechnicalName());
        }

        // Is inherited Theme -> get Plugin
        if ($pluginConfig === null) {
            if ($theme->getParentThemeId() !== null) {
                $criteria = (new Criteria())->addFilter(new EqualsFilter('id', $theme->getParentThemeId()));
                /** @var ThemeEntity $parentTheme */
                $parentTheme = $this->themeRepository->search($criteria, $context)->first();
                $pluginConfig = $this->pluginRegistry->getConfigurations()->getByTechnicalName($parentTheme->getTechnicalName());
            } else {
                $parentTheme = false;
                $pluginConfig = $this->pluginRegistry->getConfigurations()->getByTechnicalName(StorefrontPluginRegistry::BASE_THEME_NAME);
            }
            if (!$pluginConfig) {
                throw new InvalidThemeException($parentTheme ? $parentTheme->getTechnicalName() : StorefrontPluginRegistry::BASE_THEME_NAME);
            }
        }

        $pluginConfig->setThemeConfig($this->getThemeConfiguration($theme->getId(), $translate, $context));

        return $pluginConfig;
    }

    private function mergeStaticConfig(ThemeEntity $theme): array
    {
        $configuredTheme = [];

        $pluginConfig = null;
        if ($theme->getTechnicalName()) {
            $pluginConfig = $this->pluginRegistry->getConfigurations()->getByTechnicalName($theme->getTechnicalName());
        }

        if ($pluginConfig !== null) {
            $configuredTheme = $pluginConfig->getThemeConfig();
        }

        if ($theme !== null && $theme->getBaseConfig() !== null) {
            $configuredTheme = array_replace_recursive($configuredTheme, $theme->getBaseConfig());
        }

        if ($theme !== null && $theme->getConfigValues() !== null) {
            $configuredThemeFields = [];
            if (array_key_exists('fields', $configuredTheme)) {
                $configuredThemeFields = $configuredTheme['fields'];
            }
            $configuredTheme['fields'] = array_replace_recursive($configuredThemeFields, $theme->getConfigValues());
        }

        return $configuredTheme;
    }

    private function getSection($fieldConfig): string
    {
        $section = '';

        if (isset($fieldConfig['section'])) {
            $section = $fieldConfig['section'];
        }

        return $section;
    }

    private function getBlockLabel(string $blockName, array $translations)
    {
        return $translations['blocks.' . $blockName] ?? $blockName;
    }

    private function getSectionLabel(string $sectionName, array $translations)
    {
        return $translations['sections.' . $sectionName] ?? $sectionName;
    }

    private function translateLabels(array $themeConfiguration, array $translations)
    {
        foreach ($themeConfiguration as $key => &$value) {
            $value['label'] = $translations['fields.' . $key] ?? $key;
        }

        return $themeConfiguration;
    }

    private function translateHelpTexts(array $themeConfiguration, array $translations)
    {
        foreach ($themeConfiguration as $key => &$value) {
            $value['helpText'] = $translations['fields.' . $key] ?? null;
        }

        return $themeConfiguration;
    }

    /**
     * @throws InconsistentCriteriaIdsException
     */
    private function getTranslations(string $themeId, Context $context): array
    {
        /** @var ThemeEntity $theme */
        $theme = $this->themeRepository->search(new Criteria([$themeId]), $context)->get($themeId);
        $translations = $theme->getLabels() ?: [];
        if ($theme->getParentThemeId() !== null) {
            $parentTheme = $this->themeRepository->search(new Criteria([$theme->getParentThemeId()]), $context)
                ->get($theme->getParentThemeId());
            $parentTranslations = $parentTheme->getLabels() ?: [];
            $translations = array_replace_recursive($parentTranslations, $translations);
        }
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME));
        $baseTheme = $this->themeRepository->search($criteria, $context)->first();
        $baseTranslations = $baseTheme->getLabels() ?: [];
        $translations = array_replace_recursive($baseTranslations, $translations);

        return $translations;
    }
}

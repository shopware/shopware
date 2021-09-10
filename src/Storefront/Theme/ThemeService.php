<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepositoryInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Storefront\Theme\ConfigLoader\AbstractConfigLoader;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;
use Shopware\Storefront\Theme\Exception\InvalidThemeConfigException;
use Shopware\Storefront\Theme\Exception\InvalidThemeException;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ThemeService
{
    private StorefrontPluginRegistryInterface $extensionRegistery;

    private EntityRepositoryInterface $themeRepository;

    private EntityRepositoryInterface $themeSalesChannelRepository;

    private ThemeCompilerInterface $themeCompiler;

    private EventDispatcherInterface $dispatcher;

    private AbstractConfigLoader $configLoader;

    public function __construct(
        StorefrontPluginRegistryInterface $extensionRegistry,
        EntityRepositoryInterface $themeRepository,
        EntityRepositoryInterface $themeSalesChannelRepository,
        ThemeCompilerInterface $themeCompiler,
        EventDispatcherInterface $dispatcher,
        AbstractConfigLoader $configLoader
    ) {
        $this->extensionRegistery = $extensionRegistry;
        $this->themeRepository = $themeRepository;
        $this->themeSalesChannelRepository = $themeSalesChannelRepository;
        $this->themeCompiler = $themeCompiler;
        $this->dispatcher = $dispatcher;
        $this->configLoader = $configLoader;
    }

    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        Context $context,
        ?StorefrontPluginConfigurationCollection $configurationCollection = null,
        bool $withAssets = true
    ): void {
        $this->themeCompiler->compileTheme(
            $salesChannelId,
            $themeId,
            $this->configLoader->load($themeId, $context),
            $configurationCollection ?? $this->extensionRegistery->getConfigurations(),
            $withAssets
        );
    }

    public function updateTheme(string $themeId, ?array $config, ?string $parentThemeId, Context $context): void
    {
        $criteria = new Criteria([$themeId]);
        $criteria->addAssociation('salesChannels');
        /** @var ThemeEntity|null $theme */
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

        if (\array_key_exists('configValues', $data)) {
            $this->dispatcher->dispatch(new ThemeConfigChangedEvent($themeId, $data['configValues']));
        }

        if (\array_key_exists('configValues', $data) && $theme->getConfigValues()) {
            $submittedChanges = $data['configValues'];
            $currentConfig = $theme->getConfigValues();
            $data['configValues'] = array_replace_recursive($currentConfig, $data['configValues']);

            foreach ($submittedChanges as $key => $changes) {
                if (\is_array($changes['value']) && isset($currentConfig[$key]) && \is_array($currentConfig[$key])) {
                    $data['configValues'][$key]['value'] = array_unique($changes['value']);
                }
            }
        }

        $this->themeRepository->update([$data], $context);

        if ($theme->getSalesChannels() === null) {
            return;
        }

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

        $this->dispatcher->dispatch(new ThemeAssignedEvent($themeId, $salesChannelId));

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

        $this->dispatcher->dispatch(new ThemeConfigResetEvent($themeId));

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
        $criteria->setTitle('theme-service::load-config');

        $criteria->addFilter(new MultiFilter(
            MultiFilter::CONNECTION_OR,
            [
                new EqualsFilter('technicalName', StorefrontPluginRegistry::BASE_THEME_NAME),
                new EqualsFilter('id', $themeId),
            ]
        ));

        $themes = $this->themeRepository->search($criteria, $context);

        $theme = $themes->get($themeId);

        /** @var ThemeEntity|null $theme */
        if (!$theme) {
            throw new InvalidThemeException($themeId);
        }

        /** @var ThemeEntity $baseTheme */
        $baseTheme = $themes->filter(function (ThemeEntity $theme) {
            return $theme->getTechnicalName() === StorefrontPluginRegistry::BASE_THEME_NAME;
        })->first();

        $baseThemeConfig = $this->mergeStaticConfig($baseTheme);

        $themeConfigFieldFactory = new ThemeConfigFieldFactory();
        $configFields = [];

        $configuredTheme = $this->mergeStaticConfig($theme);
        $themeConfig = array_replace_recursive($baseThemeConfig, $configuredTheme);

        foreach ($themeConfig['fields'] as $name => $item) {
            $configFields[$name] = $themeConfigFieldFactory->create($name, $item);
        }

        $configFields = json_decode((string) json_encode($configFields), true);

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

    public function getThemeConfigurationStructuredFields(string $themeId, bool $translate, Context $context): array
    {
        $mergedConfig = $this->getThemeConfiguration($themeId, $translate, $context)['fields'];

        $translations = [];
        if ($translate) {
            $translations = $this->getTranslations($themeId, $context);
            $mergedConfig = $this->translateLabels($mergedConfig, $translations);
        }

        $outputStructure = [];

        foreach ($mergedConfig as $fieldName => $fieldConfig) {
            $tab = $this->getTab($fieldConfig);
            $tabLabel = $this->getTabLabel($tab, $translations);
            $block = $this->getBlock($fieldConfig);
            $blockLabel = $this->getBlockLabel($block, $translations);
            $section = $this->getSection($fieldConfig);
            $sectionLabel = $this->getSectionLabel($section, $translations);

            // set default tab
            $outputStructure['tabs']['default']['label'] = '';

            // set labels
            $outputStructure['tabs'][$tab]['label'] = $tabLabel;
            $outputStructure['tabs'][$tab]['blocks'][$block]['label'] = $blockLabel;
            $outputStructure['tabs'][$tab]['blocks'][$block]['sections'][$section]['label'] = $sectionLabel;

            // add fields to sections
            $outputStructure['tabs'][$tab]['blocks'][$block]['sections'][$section]['fields'][$fieldName] = [
                'label' => $fieldConfig['label'],
                'helpText' => $fieldConfig['helpText'] ?? null,
                'type' => $fieldConfig['type'],
                'custom' => $fieldConfig['custom'],
                'fullWidth' => $fieldConfig['fullWidth'],
            ];
        }

        return $outputStructure;
    }

    private function mergeStaticConfig(ThemeEntity $theme): array
    {
        $configuredTheme = [];

        $pluginConfig = null;
        if ($theme->getTechnicalName()) {
            $pluginConfig = $this->extensionRegistery->getConfigurations()->getByTechnicalName($theme->getTechnicalName());
        }

        if ($pluginConfig !== null) {
            $configuredTheme = $pluginConfig->getThemeConfig();
        }

        if ($theme->getBaseConfig() !== null) {
            $configuredTheme = array_replace_recursive($configuredTheme, $theme->getBaseConfig());
        }

        if ($theme->getConfigValues() !== null) {
            foreach ($theme->getConfigValues() as $fieldName => $configValue) {
                if (isset($configValue['value'])) {
                    $configuredTheme['fields'][$fieldName]['value'] = $configValue['value'];
                }
            }
        }

        return $configuredTheme;
    }

    private function getTab($fieldConfig): string
    {
        $tab = 'default';

        if (isset($fieldConfig['tab'])) {
            $tab = $fieldConfig['tab'];
        }

        return $tab;
    }

    private function getBlock($fieldConfig): string
    {
        $block = 'default';

        if (isset($fieldConfig['block'])) {
            $block = $fieldConfig['block'];
        }

        return $block;
    }

    private function getSection($fieldConfig): string
    {
        $section = 'default';

        if (isset($fieldConfig['section'])) {
            $section = $fieldConfig['section'];
        }

        return $section;
    }

    private function getTabLabel(string $tabName, array $translations)
    {
        if ($tabName === 'default') {
            return '';
        }

        return $translations['tabs.' . $tabName] ?? $tabName;
    }

    private function getBlockLabel(string $blockName, array $translations)
    {
        if ($blockName === 'default') {
            return '';
        }

        return $translations['blocks.' . $blockName] ?? $blockName;
    }

    private function getSectionLabel(string $sectionName, array $translations)
    {
        if ($sectionName === 'default') {
            return '';
        }

        return $translations['sections.' . $sectionName] ?? $sectionName;
    }

    private function translateLabels(array $themeConfiguration, array $translations): array
    {
        foreach ($themeConfiguration as $key => &$value) {
            $value['label'] = $translations['fields.' . $key] ?? $key;
        }

        return $themeConfiguration;
    }

    private function translateHelpTexts(array $themeConfiguration, array $translations): array
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

        return array_replace_recursive($baseTranslations, $translations);
    }
}

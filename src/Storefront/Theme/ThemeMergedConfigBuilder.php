<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\InconsistentCriteriaIdsException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\Exception\InvalidThemeConfigException;
use Shopware\Storefront\Theme\Exception\ThemeException;

/**
 * @internal
 */
#[Package('framework')]
class ThemeMergedConfigBuilder
{
    private ThemeCollection $themes;

    /**
     * @internal
     *
     * @param EntityRepository<ThemeCollection> $themeRepository
     */
    public function __construct(
        private readonly StorefrontPluginRegistry $extensionRegistry,
        private readonly EntityRepository $themeRepository,
    ) {
    }

    /**
     * @throws InvalidThemeConfigException
     * @throws ThemeException
     * @throws InconsistentCriteriaIdsException
     *
     * @return array<string, mixed>
     */
    public function getPlainThemeConfiguration(string $themeId, Context $context): array
    {
        $isLegacy = !Feature::isActive('v6.8.0.0');

        if ($isLegacy) {
            $translate = \func_num_args() === 3 ? func_get_arg(2) : false;
        }

        $criteria = (new Criteria())
            ->setTitle('theme-service::load-config');

        $this->themes = $this->themeRepository->search($criteria, $context)->getEntities();

        $theme = $this->themes->get($themeId);
        if (!($theme instanceof ThemeEntity)) {
            throw ThemeException::couldNotFindThemeById($themeId);
        }

        $baseTheme = $this->themes->filter(fn (ThemeEntity $themeEntry) => $themeEntry->getTechnicalName() === StorefrontPluginRegistry::BASE_THEME_NAME)->first();
        if ($baseTheme === null) {
            throw ThemeException::couldNotFindThemeByName(StorefrontPluginRegistry::BASE_THEME_NAME);
        }

        $baseThemeConfig = $this->mergeStaticConfig($baseTheme);

        $themeConfigFieldFactory = new ThemeConfigFieldFactory();
        $configFields = [];

        if ($isLegacy) {
            $labels = array_replace_recursive($baseTheme->getLabels() ?? [], $theme->getLabels() ?? []);
            $helpTexts = array_replace_recursive($baseTheme->getHelpTexts() ?? [], $theme->getHelpTexts() ?? []);
        }

        if ($theme->getParentThemeId()) {
            foreach ($this->getParentThemes($this->themes, $theme) as $parentTheme) {
                $configuredParentTheme = $this->mergeStaticConfig($parentTheme);
                $baseThemeConfig = array_replace_recursive($baseThemeConfig, $configuredParentTheme);

                if ($isLegacy) {
                    $labels = array_replace_recursive($labels, $parentTheme->getLabels() ?? []);
                    $helpTexts = array_replace_recursive($helpTexts, $parentTheme->getHelpTexts() ?? []);
                }
            }
        }

        $configuredTheme = $this->mergeStaticConfig($theme);
        $themeConfig = array_replace_recursive($baseThemeConfig, $configuredTheme);

        foreach ($themeConfig['fields'] ?? [] as $name => $item) {
            $configFields[$name] = $themeConfigFieldFactory->create($name, $item);
            if (
                isset($item['value'], $configuredTheme['fields'])
                && \is_array($item['value'])
                && \array_key_exists($name, $configuredTheme['fields'])
            ) {
                $configFields[$name]->setValue($configuredTheme['fields'][$name]['value']);
            }
        }

        $configFields = json_decode((string) json_encode($configFields, \JSON_THROW_ON_ERROR), true, 512, \JSON_THROW_ON_ERROR);

        if ($isLegacy && $translate) {
            if (!empty($labels)) {
                $configFields = $this->translateLabels($configFields, $labels);
            }

            if (!empty($helpTexts)) {
                $configFields = $this->translateHelpTexts($configFields, $helpTexts);
            }
        }

        // Check if the theme is a database copy of a physical theme.
        // If so, use the technical name of the parent theme.
        if ($theme->getTechnicalName() === null && $theme->getParentThemeId() !== null) {
            $parentTheme = $this->themes->filter(fn (ThemeEntity $themeEntry) => $themeEntry->getId() === $theme->getParentThemeId())->first();

            if ($parentTheme instanceof ThemeEntity) {
                $themeConfig['themeTechnicalName'] = $parentTheme->getTechnicalName();
            }
        } else {
            $themeConfig['themeTechnicalName'] = $theme->getTechnicalName();
        }

        $themeConfig['configInheritance'] = $this->getConfigInheritance($theme);
        $themeConfig['fields'] = $configFields;
        $themeConfig['currentFields'] = [];
        $themeConfig['baseThemeFields'] = [];

        foreach ($themeConfig['fields'] as $field => $fieldItem) {
            $isInherited = $this->fieldIsInherited($field, $configuredTheme);
            $themeConfig['currentFields'][$field]['isInherited'] = $isInherited;

            if ($isInherited) {
                $themeConfig['currentFields'][$field]['value'] = null;
            } elseif (\array_key_exists('value', $fieldItem)) {
                $themeConfig['currentFields'][$field]['value'] = $fieldItem['value'];
            }

            $isInherited = $this->fieldIsInherited($field, $baseThemeConfig);
            $themeConfig['baseThemeFields'][$field]['isInherited'] = $isInherited;

            if ($isInherited) {
                $themeConfig['baseThemeFields'][$field]['value'] = null;
            } elseif (\array_key_exists('value', $fieldItem) && isset($baseThemeConfig['fields'][$field]['value'])) {
                $themeConfig['baseThemeFields'][$field]['value'] = $baseThemeConfig['fields'][$field]['value'];
            }
        }

        // cleaning up data that we do not want to expose in the v6.8.0.0
        if (Feature::isActive('v6.8.0.0')) {
            // labels are still stored in the database, but we don't want to expose them in the response
            if (isset($themeConfig['blocks'])) {
                foreach ($themeConfig['blocks'] as &$block) {
                    unset($block['label']);
                }
            }

            // remove next block in actual migration to v6.8.0.0, as fields will be removed
            // from ThemeConfigField and resulting array will not contain them anymore
            if (isset($themeConfig['fields'])) {
                foreach ($themeConfig['fields'] as &$field) {
                    unset($field['label']);
                    unset($field['helpText']);
                }
            }
        }

        return $themeConfig;
    }

    /**
     * @return array<string, mixed>
     */
    public function getThemeConfigurationFieldStructure(string $themeId, Context $context): array
    {
        $isLegacy = !Feature::isActive('v6.8.0.0');
        if ($isLegacy) {
            $translate = \func_num_args() === 3 ? func_get_arg(2) : false;
            $themeConfig = $this->getPlainThemeConfiguration($themeId, $context, $translate);
        } else {
            $themeConfig = $this->getPlainThemeConfiguration($themeId, $context);
        }

        $themeTechnicalName = (string) $themeConfig['themeTechnicalName'];
        $mergedFieldConfig = $themeConfig['fields'];

        $translations = [];
        if ($isLegacy && $translate) {
            $translations = $this->getTranslations($themeId, $context);
            $mergedFieldConfig = $this->translateLabels($mergedFieldConfig, $translations);
        }

        $outputStructure = [];

        foreach ($mergedFieldConfig as $fieldName => $fieldConfig) {
            $tab = $this->getTab($fieldConfig);
            $block = $this->getBlock($fieldConfig);
            $section = $this->getSection($fieldConfig);

            $outputStructure = $this->addTranslations($outputStructure, $themeTechnicalName, $tab, $block, $section, $translations);

            $custom = $this->buildCustom($fieldConfig['custom'], $themeTechnicalName, $tab, $block, $section, $fieldName);

            $outputStructure['tabs'][$tab]['blocks'][$block]['sections'][$section]['fields'][$fieldName] =
                $this->buildField($fieldConfig, $custom, $themeTechnicalName, $tab, $block, $section, $fieldName);
        }

        $outputStructure['themeTechnicalName'] = $themeTechnicalName;
        $outputStructure['configInheritance'] = $themeConfig['configInheritance'];

        return $outputStructure;
    }

    /**
     * @param array<string, mixed> $fieldConfig
     * @param array<string, mixed>|null $custom
     *
     * @return array<string, mixed>
     */
    private function buildField(array $fieldConfig, ?array $custom, string $themeTechnicalName, string $tab, string $block, string $section, string $fieldName): array
    {
        $field = [
            'labelSnippetKey' => $this->buildSnippetKey(
                $themeTechnicalName,
                false,
                $tab,
                $block,
                $section,
                $fieldName,
            ),
            'helpTextSnippetKey' => $this->buildSnippetKey(
                $themeTechnicalName,
                true,
                $tab,
                $block,
                $section,
                $fieldName,
            ),
            'type' => $fieldConfig['type'] ?? null,
            'custom' => $custom,
            'fullWidth' => $fieldConfig['fullWidth'],
        ];

        if (!Feature::isActive('v6.8.0.0')) {
            $field['label'] = $fieldConfig['label'];
            $field['helpText'] = $fieldConfig['helpText'] ?? null;
        }

        return $field;
    }

    /**
     * @param array<string, ThemeEntity> $parentThemes
     *
     * @return array<string, ThemeEntity>
     */
    private function getParentThemes(ThemeCollection $themes, ThemeEntity $mainTheme, array $parentThemes = []): array
    {
        foreach ($this->getConfigInheritance($mainTheme) as $parentThemeName) {
            $parentTheme = $themes->filter(fn (ThemeEntity $themeEntry) => $themeEntry->getTechnicalName() === str_replace('@', '', (string) $parentThemeName))->first();

            if ($parentTheme instanceof ThemeEntity && !\array_key_exists($parentTheme->getId(), $parentThemes)) {
                $parentThemes[$parentTheme->getId()] = $parentTheme;

                if ($parentTheme->getParentThemeId()) {
                    $parentThemes = $this->getParentThemes($themes, $mainTheme, $parentThemes);
                }
            }
        }

        if ($mainTheme->getParentThemeId()) {
            $parentTheme = $themes->filter(fn (ThemeEntity $themeEntry) => $themeEntry->getId() === $mainTheme->getParentThemeId())->first();

            if ($parentTheme instanceof ThemeEntity && !\array_key_exists($parentTheme->getId(), $parentThemes)) {
                $parentThemes[$parentTheme->getId()] = $parentTheme;
                if ($parentTheme->getParentThemeId()) {
                    $parentThemes = $this->getParentThemes($themes, $mainTheme, $parentThemes);
                }
            }
        }

        return $parentThemes;
    }

    /**
     * @return array<int, string>
     */
    private function getConfigInheritance(ThemeEntity $mainTheme): array
    {
        $baseConfig = $mainTheme->getBaseConfig();

        if (\is_array($baseConfig)
            && \array_key_exists('configInheritance', $baseConfig)
            && \is_array($baseConfig['configInheritance'])
            && !empty($baseConfig['configInheritance'])
        ) {
            return $baseConfig['configInheritance'];
        }

        // For database copies (child themes), inherit config from parent theme.
        if (
            $baseConfig === null
            && $mainTheme->getTechnicalName() === null
            && $mainTheme->getParentThemeId() !== null
        ) {
            $parentId = $mainTheme->getParentThemeId();
            $parentTheme = $this->themes->get($parentId);

            if ($parentTheme instanceof ThemeEntity) {
                $parentConfigInheritance = $this->getConfigInheritance($parentTheme);
                if (!empty($parentConfigInheritance)) {
                    return $parentConfigInheritance;
                }
            }
        }

        // Fallback: ensure every theme (except base theme) inherits from Storefront by default
        if ($mainTheme->getTechnicalName() !== StorefrontPluginRegistry::BASE_THEME_NAME) {
            return [
                '@' . StorefrontPluginRegistry::BASE_THEME_NAME,
            ];
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function mergeStaticConfig(ThemeEntity $theme): array
    {
        $configuredTheme = [];

        $pluginConfig = null;
        if ($theme->getTechnicalName()) {
            $pluginConfig = $this->extensionRegistry->getConfigurations()->getByTechnicalName($theme->getTechnicalName());
        }

        if ($pluginConfig !== null) {
            $configuredTheme = $pluginConfig->getThemeConfig();
        }

        if ($theme->getBaseConfig() !== null) {
            $configuredTheme = array_replace_recursive($configuredTheme ?? [], $theme->getBaseConfig());
        }

        if ($theme->getConfigValues() !== null) {
            foreach ($theme->getConfigValues() as $fieldName => $configValue) {
                if (\array_key_exists('value', $configValue)) {
                    $configuredTheme['fields'][$fieldName]['value'] = $configValue['value'];
                }
            }
        }

        return $configuredTheme ?: [];
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    private function getTab(array $fieldConfig): string
    {
        $tab = 'default';

        if (isset($fieldConfig['tab'])) {
            $tab = $fieldConfig['tab'];
        }

        return $tab;
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    private function getBlock(array $fieldConfig): string
    {
        $block = 'default';

        if (isset($fieldConfig['block'])) {
            $block = $fieldConfig['block'];
        }

        return $block;
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    private function getSection(array $fieldConfig): string
    {
        $section = 'default';

        if (isset($fieldConfig['section'])) {
            $section = $fieldConfig['section'];
        }

        return $section;
    }

    /**
     * @param array<string, mixed> $translations
     */
    private function getTabLabel(string $tabName, array $translations): string
    {
        if ($tabName === 'default') {
            return '';
        }

        return $translations['tabs.' . $tabName] ?? $tabName;
    }

    /**
     * @param array<string, mixed> $translations
     */
    private function getBlockLabel(string $blockName, array $translations): string
    {
        if ($blockName === 'default') {
            return '';
        }

        return $translations['blocks.' . $blockName] ?? $blockName;
    }

    /**
     * @param array<string, mixed> $translations
     */
    private function getSectionLabel(string $sectionName, array $translations): string
    {
        if ($sectionName === 'default') {
            return '';
        }

        return $translations['sections.' . $sectionName] ?? $sectionName;
    }

    /**
     * @param array<string, mixed> $themeConfiguration
     * @param array<string, mixed> $translations
     *
     * @return array<string, mixed>
     */
    private function translateLabels(array $themeConfiguration, array $translations): array
    {
        foreach ($themeConfiguration as $key => &$value) {
            $value['label'] = $translations['fields.' . $key] ?? $key;
        }

        return $themeConfiguration;
    }

    /**
     * @param array<string, mixed> $themeConfiguration
     * @param array<string, mixed> $translations
     *
     * @return array<string, mixed>
     */
    private function translateHelpTexts(array $themeConfiguration, array $translations): array
    {
        foreach ($themeConfiguration as $key => &$value) {
            $value['helpText'] = $translations['fields.' . $key] ?? null;
        }

        return $themeConfiguration;
    }

    /**
     * @return array<string, mixed>
     */
    private function getTranslations(string $themeId, Context $context): array
    {
        $theme = $this->themeRepository->search(new Criteria([$themeId]), $context)->getEntities()->first();
        if (!$theme) {
            throw ThemeException::couldNotFindThemeById($themeId);
        }

        $translations = $theme->getLabels() ?: [];

        if ($theme->getTechnicalName() !== StorefrontPluginRegistry::BASE_THEME_NAME) {
            $criteria = (new Criteria())
                ->setTitle('theme-service::load-translations');

            $themes = $this->themeRepository->search($criteria, $context)->getEntities();
            foreach ($this->getParentThemes($themes, $theme) as $parentTheme) {
                $parentTranslations = $parentTheme->getLabels() ?: [];
                $translations = array_replace_recursive($parentTranslations, $translations);
            }
        }

        return $translations;
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function fieldIsInherited(string $fieldName, array $configuration): bool
    {
        if (!isset($configuration['fields'])) {
            return true;
        }

        if (!\is_array($configuration['fields'])) {
            return true;
        }

        if (!\array_key_exists($fieldName, $configuration['fields'])) {
            return true;
        }

        return false;
    }

    private function buildSnippetKey(string $themeTechnicalName, bool $isHelpText, string ...$parts): string
    {
        return implode(
            '.',
            [
                ...$parts,
                $isHelpText ? 'helpText' : 'label',
            ],
        );
    }

    /**
     * @param array<string,mixed>|null $custom
     * @param string $themeTechnicalName
     *
     * @return ?array<string, mixed>
     */
    private function buildCustom(
        ?array $custom,
        mixed $themeTechnicalName,
        string $tab,
        string $block,
        string $section,
        string $fieldName
    ): ?array {
        $custom = $custom ?? null;

        if ($custom && isset($custom['options']) && \is_array($custom['options'])) {
            foreach ($custom['options'] as $optionIndex => &$option) {
                $option['labelSnippetKey'] = $this->buildSnippetKey(
                    $themeTechnicalName,
                    false,
                    $tab,
                    $block,
                    $section,
                    $fieldName,
                    (string) $optionIndex,
                );
            }
            unset($option);
        }

        return $custom;
    }

    /**
     * @param array<string, mixed> $outputStructure
     * @param array<string, mixed> $translations
     *
     * @return array<string, mixed>
     */
    private function addTranslations(
        array $outputStructure,
        string $themeTechnicalName,
        string $tab,
        string $block,
        string $section,
        array $translations,
    ): array {
        $tabSnippetKey = $this->buildSnippetKey($themeTechnicalName, false, $tab);
        $blockSnippetKey = $this->buildSnippetKey($themeTechnicalName, false, $tab, $block);
        $sectionSnippetKey = $this->buildSnippetKey($themeTechnicalName, false, $tab, $block, $section);

        // set labels
        $outputStructure['tabs'][$tab]['labelSnippetKey'] = $tabSnippetKey;
        $outputStructure['tabs'][$tab]['blocks'][$block]['labelSnippetKey'] = $blockSnippetKey;
        $outputStructure['tabs'][$tab]['blocks'][$block]['sections'][$section]['labelSnippetKey'] = $sectionSnippetKey;

        if (!Feature::isActive('v6.8.0.0')) {
            // set default tab
            $outputStructure['tabs']['default']['label'] = '';

            // set labels
            $tabLabel = $this->getTabLabel($tab, $translations);
            $blockLabel = $this->getBlockLabel($block, $translations);
            $sectionLabel = $this->getSectionLabel($section, $translations);
            $outputStructure['tabs'][$tab]['label'] = $tabLabel;
            $outputStructure['tabs'][$tab]['blocks'][$block]['label'] = $blockLabel;
            $outputStructure['tabs'][$tab]['blocks'][$block]['sections'][$section]['label'] = $sectionLabel;
        }

        return $outputStructure;
    }
}

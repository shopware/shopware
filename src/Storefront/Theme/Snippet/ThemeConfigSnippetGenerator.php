<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Snippet;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Snippet\SnippetPatterns;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistry;
use Shopware\Storefront\Theme\ThemeConfigStructure;

/**
 * Turns the deprecated `label` and `helpText` translations of a theme.json into
 * administration snippets, keyed exactly like the theme manager resolves them and
 * grouped by the locale the theme.json uses (`de-DE`, `en-GB`), one snippet file each.
 *
 * Labels are taken from the theme's own theme.json only, but the position of a block, section or
 * field inside the hierarchy comes from the fields of the whole inheritance chain, so a child theme
 * can relabel groups and fields it inherits without redefining them.
 *
 * @internal
 */
#[Package('discovery')]
class ThemeConfigSnippetGenerator
{
    private const JSON_FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR;

    public function __construct(private readonly StorefrontPluginRegistry $pluginRegistry)
    {
    }

    /**
     * @return array<string, array<string, mixed>> locale => nested snippet structure; empty when the theme has no legacy translations
     */
    public function generate(StorefrontPluginConfiguration $configuration): array
    {
        $translations = $this->collectTranslations($configuration)['translations'];

        $snippets = [];
        foreach ($translations as $locale => $flatSnippets) {
            foreach ($flatSnippets as $key => $value) {
                $path = [ThemeConfigStructure::SNIPPET_KEY_PREFIX, $configuration->getTechnicalName(), ...explode('.', $key)];
                $snippets[$locale] ??= [];
                $this->setNested($snippets[$locale], $path, $value);
            }
        }

        return $snippets;
    }

    /**
     * Blocks and sections that carry a legacy label but are used by no field of the theme or its parents.
     * Their label has no position in the hierarchy, so no snippet key exists for it.
     *
     * @return list<string> e.g. "blocks.primary"
     */
    public function findUnplaceableGroups(StorefrontPluginConfiguration $configuration): array
    {
        return $this->collectTranslations($configuration)['unplaceable'];
    }

    public function fileName(string $locale): string
    {
        return $locale . '.json';
    }

    /**
     * @param array<string, mixed> $snippets
     */
    public function encode(array $snippets): string
    {
        return \json_encode($snippets, self::JSON_FLAGS) . "\n";
    }

    /**
     * @return array{translations: array<string, array<string, string>>, unplaceable: list<string>}
     */
    private function collectTranslations(StorefrontPluginConfiguration $configuration): array
    {
        $config = $this->configOf($configuration);
        if ($config === null) {
            return ['translations' => [], 'unplaceable' => []];
        }

        $translations = [];
        $placed = ['blocks' => [], 'sections' => []];

        foreach ($config['tabs'] ?? [] as $tab => $tabConfig) {
            $this->collect($translations, $tabConfig['label'] ?? null, ThemeConfigStructure::buildLabelSnippetKey((string) $tab));
        }

        foreach ($this->mergeInheritedFields($configuration) as $fieldName => $fieldConfig) {
            if (!\is_array($fieldConfig)) {
                continue;
            }

            $fieldName = (string) $fieldName;
            $tab = ThemeConfigStructure::getTab($fieldConfig);
            $block = ThemeConfigStructure::getBlock($fieldConfig);
            $section = ThemeConfigStructure::getSection($fieldConfig);
            $placed['blocks'][$block] = true;
            $placed['sections'][$section] = true;

            $this->collect($translations, $config['blocks'][$block]['label'] ?? null, ThemeConfigStructure::buildLabelSnippetKey($tab, $block));
            $this->collect($translations, $config['sections'][$section]['label'] ?? null, ThemeConfigStructure::buildLabelSnippetKey($tab, $block, $section));

            $ownField = $config['fields'][$fieldName] ?? null;
            if (!\is_array($ownField)) {
                continue;
            }

            $this->collect($translations, $ownField['label'] ?? null, ThemeConfigStructure::buildLabelSnippetKey($tab, $block, $section, $fieldName));
            $this->collect($translations, $ownField['helpText'] ?? null, ThemeConfigStructure::buildHelpTextSnippetKey($tab, $block, $section, $fieldName));

            foreach ($ownField['custom']['options'] ?? [] as $index => $option) {
                $this->collect(
                    $translations,
                    $option['label'] ?? null,
                    ThemeConfigStructure::buildLabelSnippetKey($tab, $block, $section, $fieldName, (string) $index),
                );
            }
        }

        $unplaceable = [];
        foreach (['blocks', 'sections'] as $group) {
            foreach ($config[$group] ?? [] as $name => $groupConfig) {
                if (isset($groupConfig['label']) && !isset($placed[$group][$name])) {
                    $unplaceable[] = $group . '.' . $name;
                }
            }
        }

        return ['translations' => $translations, 'unplaceable' => $unplaceable];
    }

    /**
     * Fields of the parents first, the theme's own fields on top, so a child that only overrides
     * a label keeps the tab, block and section of the inherited field.
     *
     * @param array<string, true> $visited
     *
     * @return array<string, mixed>
     */
    private function mergeInheritedFields(StorefrontPluginConfiguration $configuration, array &$visited = []): array
    {
        $visited[$configuration->getTechnicalName()] = true;
        $fields = [];

        foreach ($this->getParentNames($configuration) as $parentName) {
            if (isset($visited[$parentName])) {
                continue;
            }

            $parent = $this->pluginRegistry->getByTechnicalName($parentName);
            if ($parent === null) {
                continue;
            }

            $fields = array_replace_recursive($fields, $this->mergeInheritedFields($parent, $visited));
        }

        $ownFields = $this->configOf($configuration)['fields'] ?? null;

        return array_replace_recursive($fields, \is_array($ownFields) ? $ownFields : []);
    }

    /**
     * @return list<string>
     */
    private function getParentNames(StorefrontPluginConfiguration $configuration): array
    {
        $inheritance = $configuration->getConfigInheritance();
        if ($inheritance === [] && $configuration->getTechnicalName() !== StorefrontPluginRegistry::BASE_THEME_NAME) {
            $inheritance = ['@' . StorefrontPluginRegistry::BASE_THEME_NAME];
        }

        return array_values(array_map(static fn (string $name): string => ltrim($name, '@'), $inheritance));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function configOf(StorefrontPluginConfiguration $configuration): ?array
    {
        $config = $configuration->getThemeJson()['config'] ?? $configuration->getThemeConfig();

        return \is_array($config) ? $config : null;
    }

    /**
     * @param array<string, array<string, string>> $translations
     */
    private function collect(array &$translations, mixed $localizedValues, string $snippetKey): void
    {
        if (!\is_array($localizedValues)) {
            return;
        }

        foreach ($localizedValues as $locale => $value) {
            if (!\is_string($locale) || !\is_string($value) || !preg_match(SnippetPatterns::COMPLETE_LOCALE_PATTERN, $locale)) {
                continue;
            }

            $translations[$locale][$snippetKey] = $value;
        }
    }

    /**
     * @param array<string, mixed> $target
     * @param non-empty-list<string> $path
     */
    private function setNested(array &$target, array $path, string $value): void
    {
        $leaf = array_pop($path);
        $cursor = &$target;

        foreach ($path as $segment) {
            if (!\is_array($cursor[$segment] ?? null)) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        $cursor[$leaf] = $value;
    }
}

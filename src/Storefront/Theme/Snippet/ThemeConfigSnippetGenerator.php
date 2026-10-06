<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\Snippet;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\ThemeConfigStructure;

/**
 * Turns the deprecated `label` and `helpText` translations of a theme.json into
 * administration snippets, keyed exactly like the theme manager resolves them and
 * grouped by the locale the theme.json uses (`de-DE`, `en-GB`), one snippet file each.
 *
 * @internal
 */
#[Package('discovery')]
class ThemeConfigSnippetGenerator
{
    private const JSON_FLAGS = \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR;

    /**
     * @return array<string, array<string, mixed>> locale => nested snippet structure; empty when the theme has no legacy translations
     */
    public function generate(StorefrontPluginConfiguration $configuration): array
    {
        $config = $configuration->getThemeJson()['config'] ?? null;
        if (!\is_array($config) || !\is_array($config['fields'] ?? null)) {
            return [];
        }

        $translations = [];
        foreach ($config['fields'] as $fieldName => $fieldConfig) {
            if (!\is_array($fieldConfig)) {
                continue;
            }

            $fieldName = (string) $fieldName;
            $tab = ThemeConfigStructure::getTab($fieldConfig);
            $block = ThemeConfigStructure::getBlock($fieldConfig);
            $section = ThemeConfigStructure::getSection($fieldConfig);

            $this->collect($translations, $config['tabs'][$tab]['label'] ?? null, ThemeConfigStructure::buildLabelSnippetKey($tab));
            $this->collect($translations, $config['blocks'][$block]['label'] ?? null, ThemeConfigStructure::buildLabelSnippetKey($tab, $block));
            $this->collect($translations, $config['sections'][$section]['label'] ?? null, ThemeConfigStructure::buildLabelSnippetKey($tab, $block, $section));

            $this->collect($translations, $fieldConfig['label'] ?? null, ThemeConfigStructure::buildLabelSnippetKey($tab, $block, $section, $fieldName));
            $this->collect($translations, $fieldConfig['helpText'] ?? null, ThemeConfigStructure::buildHelpTextSnippetKey($tab, $block, $section, $fieldName));

            foreach ($fieldConfig['custom']['options'] ?? [] as $index => $option) {
                $this->collect(
                    $translations,
                    $option['label'] ?? null,
                    ThemeConfigStructure::buildLabelSnippetKey($tab, $block, $section, $fieldName, (string) $index),
                );
            }
        }

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
     * @param array<string, array<string, string>> $translations
     */
    private function collect(array &$translations, mixed $localizedValues, string $snippetKey): void
    {
        if (!\is_array($localizedValues)) {
            return;
        }

        foreach ($localizedValues as $locale => $value) {
            if (!\is_string($locale) || !\is_string($value)) {
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

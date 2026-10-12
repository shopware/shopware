<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;

/**
 * Resolves the tab/block/section hierarchy of a theme.json field and builds the
 * administration snippet keys the theme manager derives from that hierarchy.
 *
 * @internal
 */
#[Package('discovery')]
final class ThemeConfigStructure
{
    public const DEFAULT_GROUP = 'default';

    public const SNIPPET_KEY_PREFIX = 'sw-theme';

    /**
     * @param array<string, mixed> $fieldConfig
     */
    public static function getTab(array $fieldConfig): string
    {
        return self::getGroup($fieldConfig, 'tab');
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    public static function getBlock(array $fieldConfig): string
    {
        return self::getGroup($fieldConfig, 'block');
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    public static function getSection(array $fieldConfig): string
    {
        return self::getGroup($fieldConfig, 'section');
    }

    public static function buildLabelSnippetKey(string ...$parts): string
    {
        return implode('.', [...$parts, 'label']);
    }

    public static function buildHelpTextSnippetKey(string ...$parts): string
    {
        return implode('.', [...$parts, 'helpText']);
    }

    /**
     * @param array<string, mixed> $fieldConfig
     */
    private static function getGroup(array $fieldConfig, string $key): string
    {
        if (isset($fieldConfig[$key]) && \is_string($fieldConfig[$key])) {
            return $fieldConfig[$key];
        }

        return self::DEFAULT_GROUP;
    }
}

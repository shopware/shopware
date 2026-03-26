<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

#[Package('framework')]
interface ThemeCompilerInterface
{
    public function compileTheme(
        string $salesChannelId,
        string $themeId,
        StorefrontPluginConfiguration $themeConfig,
        StorefrontPluginConfigurationCollection $configurationCollection,
        bool $withAssets,
        Context $context
    ): void;

    /**
     * Builds the pre-computed component import map for storage in ThemeRuntimeConfig.
     *
     * Returns a structure with theme-relative paths (e.g. 'js/components/Sw/Filter/Sorting.js')
     * for all component entries and, where present, vendor chunk scopes for extensions.
     * TemplateConfigAccessor converts these paths to full URLs at request time via
     * Symfony's asset Packages service — no URL computation happens here.
     *
     * Returns null when no Vite build is present (first-run / test environment without a build).
     *
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>}|null
     */
    public function buildComponentImportMap(): ?array;
}

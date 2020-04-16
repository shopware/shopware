<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

interface ThemeCompilerInterface
{
    public function compileTheme(string $salesChannelId, string $themeId, StorefrontPluginConfiguration $themeConfig, StorefrontPluginConfigurationCollection $configurationCollection, bool $withAssets = true): void;
}

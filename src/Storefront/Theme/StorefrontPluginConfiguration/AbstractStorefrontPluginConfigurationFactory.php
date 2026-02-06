<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
abstract class AbstractStorefrontPluginConfigurationFactory
{
    abstract public function getDecorated(): AbstractStorefrontPluginConfigurationFactory;

    /**
     * Creates one or more theme configurations from a bundle.
     * Most bundles return a single configuration.
     * Special bundles (like Storefront) may return multiple configurations to support multiple themes.
     *
     * @return StorefrontPluginConfiguration|array<StorefrontPluginConfiguration>
     */
    abstract public function createFromBundle(Bundle $bundle): StorefrontPluginConfiguration|array;

    abstract public function createFromApp(string $appName, string $appPath): StorefrontPluginConfiguration;

    /**
     * @param array<string, mixed> $data
     */
    abstract public function createFromThemeJson(string $name, array $data, string $path): StorefrontPluginConfiguration;
}

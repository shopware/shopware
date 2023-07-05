<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;

#[Package('storefront')]
abstract class AbstractStorefrontPluginConfigurationFactory
{
    abstract public function getDecorated(): AbstractStorefrontPluginConfigurationFactory;

    abstract public function createFromBundle(Bundle $bundle): StorefrontPluginConfiguration;

    abstract public function createFromApp(string $appName, string $appPath): StorefrontPluginConfiguration;

    /**
     * @decrecated tag:v6.6.0 - will be abstract, implementation has to be in the concrete class
     *
     * @param array<string, mixed> $data
     */
    public function createFromThemeJson(string $name, array $data, string $path, bool $isFullpath = true): StorefrontPluginConfiguration
    {
        return new StorefrontPluginConfiguration($name);
    }
}

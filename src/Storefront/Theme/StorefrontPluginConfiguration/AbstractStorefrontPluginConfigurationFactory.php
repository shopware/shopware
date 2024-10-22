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
     * @param array<string, mixed> $data
     */
    abstract public function createFromThemeJson(string $name, array $data, string $path): StorefrontPluginConfiguration;
}

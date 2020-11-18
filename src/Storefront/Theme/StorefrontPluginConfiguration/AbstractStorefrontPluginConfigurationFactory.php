<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Bundle;

abstract class AbstractStorefrontPluginConfigurationFactory
{
    abstract public function getDecorated(): AbstractStorefrontPluginConfigurationFactory;

    abstract public function createFromBundle(Bundle $bundle): StorefrontPluginConfiguration;

    abstract public function createFromApp(string $appName, string $appPath): StorefrontPluginConfiguration;
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

#[Package('storefront')]
interface StorefrontPluginRegistryInterface
{
    public function getConfigurations(): StorefrontPluginConfigurationCollection;
}

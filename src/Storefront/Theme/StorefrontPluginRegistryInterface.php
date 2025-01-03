<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

/**
 * @deprecated tag:v6.7.0 - Will be removed
 */
#[Package('storefront')]
interface StorefrontPluginRegistryInterface
{
    public function getConfigurations(): StorefrontPluginConfigurationCollection;
}

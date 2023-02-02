<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

/**
 * @Decoratable
 */
interface StorefrontPluginRegistryInterface
{
    public function getConfigurations(): StorefrontPluginConfigurationCollection;
}

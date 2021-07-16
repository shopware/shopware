<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\ConfigLoader;

use Shopware\Core\Framework\Context;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

abstract class AbstractConfigLoader
{
    abstract public function getDecorated(): AbstractConfigLoader;

    abstract public function load(string $themeId, Context $context): StorefrontPluginConfiguration;
}

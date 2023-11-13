<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\ConfigLoader;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;

#[Package('storefront')]
abstract class AbstractConfigLoader
{
    abstract public function getDecorated(): AbstractConfigLoader;

    abstract public function load(string $themeId, Context $context): StorefrontPluginConfiguration;
}

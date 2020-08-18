<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\App\AppEntity;
use Shopware\Core\Framework\Bundle;

abstract class AbstractStorefrontPluginConfigurationFactory
{
    abstract public function createFromBundle(Bundle $bundle): StorefrontPluginConfiguration;

    abstract public function createFromApp(AppEntity $app): StorefrontPluginConfiguration;
}

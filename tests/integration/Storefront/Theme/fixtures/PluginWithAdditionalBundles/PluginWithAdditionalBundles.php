<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Storefront\Theme\fixtures\PluginWithAdditionalBundles;

use Shopware\Core\Framework\Parameter\AdditionalBundleParameters;
use Shopware\Core\Framework\Plugin;
use Shopware\Tests\Integration\Storefront\Theme\fixtures\PluginWithAdditionalBundles\SubBundle1\SubBundle1;

/**
 * @internal
 */
class PluginWithAdditionalBundles extends Plugin
{
    public function getAdditionalBundles(AdditionalBundleParameters $additionalBundleParameters): array
    {
        return [
            new SubBundle1(),
        ];
    }
}

<?php

namespace Shopware\Tests\Integration\Storefront\Theme\fixtures;

use Shopware\Core\Framework\Context;
use Shopware\Storefront\Theme\ConfigLoader\DatabaseRuntimeConfigLoader;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;

class StaticRuntimeConfigLoader extends DatabaseRuntimeConfigLoader
{
    /** @var array<StorefrontPluginConfiguration> */
    private StorefrontPluginConfigurationCollection $config;

    public function __construct()
    { }

    public function loadById(string $themeId, Context $context): ?StorefrontPluginConfiguration
    {
        throw new \RuntimeException('Not implemented');
    }

    public function loadByTechnicalName(string $technicalName, Context $context): ?StorefrontPluginConfiguration
    {
        foreach ($this->config as $config) {
            if ($config->getTechnicalName() === $technicalName) {
                return $config;
            }
        }

        return null;
    }

    public function setConfiguration(StorefrontPluginConfigurationCollection $config): void
    {
        $this->config = $config;
    }
}

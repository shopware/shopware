<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme\StorefrontPluginConfiguration;

use Shopware\Core\Framework\Struct\Collection;

/**
 * @extends Collection<StorefrontPluginConfiguration>
 */
class StorefrontPluginConfigurationCollection extends Collection
{
    public function getByTechnicalName(string $name): ?StorefrontPluginConfiguration
    {
        return $this->filter(function (StorefrontPluginConfiguration $config) use ($name) {
            return $config->getTechnicalName() === $name;
        })->first();
    }

    public function getThemes(): StorefrontPluginConfigurationCollection
    {
        return $this->filter(function (StorefrontPluginConfiguration $configuration) {
            return $configuration->getIsTheme();
        });
    }

    public function getNoneThemes(): StorefrontPluginConfigurationCollection
    {
        return $this->filter(function (StorefrontPluginConfiguration $configuration) {
            return !$configuration->getIsTheme();
        });
    }

    protected function getExpectedClass(): ?string
    {
        return StorefrontPluginConfiguration::class;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * @internal
 */
#[Package('framework')]
class StorefrontPluginRegistry implements ResetInterface
{
    final public const BASE_THEME_NAME = 'Storefront';

    private ?StorefrontPluginConfigurationCollection $pluginConfigurations = null;

    /**
     * @internal
     */
    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly AbstractStorefrontPluginConfigurationFactory $pluginConfigurationFactory,
        private readonly ActiveAppsLoader $activeAppsLoader
    ) {
    }

    /**
     * This method loads and parses all theme.json files from all plugins and apps
     * especially for apps where the source can be stored remotely this is expensive and therefore
     * should be used when really all configurations are needed, e.g. during theme compile
     */
    public function getConfigurations(): StorefrontPluginConfigurationCollection
    {
        if ($this->pluginConfigurations) {
            return $this->pluginConfigurations;
        }

        $this->pluginConfigurations = new StorefrontPluginConfigurationCollection();

        $this->addPluginConfigs();
        $this->addAppConfigs();

        return $this->pluginConfigurations ?? new StorefrontPluginConfigurationCollection();
    }

    /**
     * used to fetch one particular config without loading and parsing all else
     */
    public function getByTechnicalName(string $technicalName): ?StorefrontPluginConfiguration
    {
        if ($this->pluginConfigurations) {
            return $this->pluginConfigurations->getByTechnicalName($technicalName);
        }

        if ($pluginConfig = $this->getPluginConfigByTechnicalName($technicalName)) {
            return $pluginConfig;
        }

        return $this->getAppConfigByTechnicalName($technicalName);
    }

    public function reset(): void
    {
        $this->pluginConfigurations = null;
    }

    private function addPluginConfigs(): void
    {
        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            $configOrConfigs = $this->pluginConfigurationFactory->createFromBundle($bundle);

            // Handle both single config and array of configs (for multi-theme bundles like Storefront)
            if (\is_array($configOrConfigs)) {
                foreach ($configOrConfigs as $config) {
                    $this->pluginConfigurations === null ?: $this->pluginConfigurations->add($config);
                }
            } else {
                $this->pluginConfigurations === null ?: $this->pluginConfigurations->add($configOrConfigs);
            }
        }
    }

    private function addAppConfigs(): void
    {
        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            if ($app['selfManaged']) {
                continue;
            }

            $config = $this->pluginConfigurationFactory->createFromApp($app['name'], $app['path']);

            $this->pluginConfigurations === null ?: $this->pluginConfigurations->add($config);
        }
    }

    private function getPluginConfigByTechnicalName(string $technicalName): ?StorefrontPluginConfiguration
    {
        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }

            // Direct bundle name match
            if ($bundle->getName() === $technicalName) {
                $config = $this->pluginConfigurationFactory->createFromBundle($bundle);
                
                // If it's a single config, return it
                if (!is_array($config)) {
                    return $config;
                }
                
                // If it's an array, find the one with matching technical name
                foreach ($config as $singleConfig) {
                    if ($singleConfig->getTechnicalName() === $technicalName) {
                        return $singleConfig;
                    }
                }
                
                // Return first one as fallback
                return $config[0] ?? null;
            }

            // Check if this technical name belongs to a multi-theme bundle
            // For example: "StorefrontExperience" should match bundle "Storefront"
            if (str_starts_with($technicalName, $bundle->getName())) {
                $configOrConfigs = $this->pluginConfigurationFactory->createFromBundle($bundle);
                
                // If it's a single config, it doesn't match
                if (!is_array($configOrConfigs)) {
                    continue;
                }
                
                // Search through all configs for matching technical name
                foreach ($configOrConfigs as $config) {
                    if ($config->getTechnicalName() === $technicalName) {
                        return $config;
                    }
                }
            }
        }

        return null;
    }

    private function getAppConfigByTechnicalName(string $technicalName): ?StorefrontPluginConfiguration
    {
        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            if ($app['name'] !== $technicalName) {
                continue;
            }

            return $this->pluginConfigurationFactory->createFromApp($app['name'], $app['path']);
        }

        return null;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\Bundle;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\AbstractStorefrontPluginConfigurationFactory;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Service\ResetInterface;

#[Package('storefront')]
class StorefrontPluginRegistry implements StorefrontPluginRegistryInterface, ResetInterface
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

            $config = $this->pluginConfigurationFactory->createFromBundle($bundle);

            $this->pluginConfigurations === null ?: $this->pluginConfigurations->add($config);
        }
    }

    private function addAppConfigs(): void
    {
        foreach ($this->activeAppsLoader->getActiveApps() as $app) {
            $config = $this->pluginConfigurationFactory->createFromApp($app['name'], $app['path']);

            $this->pluginConfigurations === null ?: $this->pluginConfigurations->add($config);
        }
    }
}

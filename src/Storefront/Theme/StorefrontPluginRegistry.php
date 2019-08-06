<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Bundle;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Symfony\Component\HttpKernel\KernelInterface;

class StorefrontPluginRegistry
{
    const BASE_THEME_NAME = 'Storefront';

    /**
     * @var StorefrontPluginConfigurationCollection|null
     */
    private $pluginConfigurations;

    /**
     * @var KernelInterface
     */
    private $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function getConfigurations(): StorefrontPluginConfigurationCollection
    {
        if ($this->pluginConfigurations) {
            return $this->pluginConfigurations;
        }

        $this->pluginConfigurations = new StorefrontPluginConfigurationCollection();

        foreach ($this->kernel->getBundles() as $bundle) {
            if (!$bundle instanceof Bundle) {
                continue;
            }
            $configPath = $bundle->getPath() . DIRECTORY_SEPARATOR . ltrim($bundle->getThemeConfigPath(), DIRECTORY_SEPARATOR);

            if (file_exists($configPath)) {
                $config = StorefrontPluginConfiguration::createFromConfigFile($configPath, $bundle);
            } else {
                $config = StorefrontPluginConfiguration::createFromBundle($bundle);

                if (count($config->getStyleFiles()) === 0 && count($config->getScriptFiles()) === 0) {
                    continue;
                }
            }

            $this->pluginConfigurations->add($config);
        }
    }
}

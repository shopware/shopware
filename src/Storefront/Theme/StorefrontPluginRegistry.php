<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Bundle;
use Shopware\Core\System\Annotation\Concept\ExtensionPattern\Decoratable;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationCollection;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfigurationFactory;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * @Decoratable
 */
class StorefrontPluginRegistry implements StorefrontPluginRegistryInterface
{
    public const BASE_THEME_NAME = 'Storefront';

    /**
     * @var StorefrontPluginConfigurationCollection|null
     */
    private $pluginConfigurations;

    /**
     * @var KernelInterface
     */
    private $kernel;

    /**
     * @var StorefrontPluginConfigurationFactory
     */
    private $pluginConfigurationFactory;

    public function __construct(
        KernelInterface $kernel,
        StorefrontPluginConfigurationFactory $pluginConfigurationFactory
    ) {
        $this->kernel = $kernel;
        $this->pluginConfigurationFactory = $pluginConfigurationFactory;
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

            $config = $this->pluginConfigurationFactory->createFromBundle($bundle);

            if (!$config->getIsTheme() && !$config->hasFilesToCompile()) {
                continue;
            }

            $this->pluginConfigurations->add($config);
        }

        return $this->pluginConfigurations;
    }
}

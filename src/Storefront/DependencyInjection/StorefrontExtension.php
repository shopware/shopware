<?php declare(strict_types=1);

namespace Shopware\Storefront\DependencyInjection;

use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Theme\AbstractThemePathBuilder;
use Shopware\Storefront\Theme\ConfigLoader\AbstractAvailableThemeProvider;
use Shopware\Storefront\Theme\ConfigLoader\AbstractConfigLoader;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;

#[Package('storefront')]
class StorefrontExtension extends Extension
{
    /**
     * Loads a specific configuration.
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();

        $config = $this->processConfiguration($configuration, $configs);

        $this->addConfig($container, 'storefront', $config);
        $this->registerThemeServiceAliases($config['theme'], $container);
    }

    private function addConfig(ContainerBuilder $container, string $alias, array $options): void
    {
        foreach ($options as $key => $option) {
            $container->setParameter($alias . '.' . $key, $option);

            if (\is_array($option)) {
                $this->addConfig($container, $alias . '.' . $key, $option);
            }
        }
    }

    private function registerThemeServiceAliases(array $theme, ContainerBuilder $container): void
    {
        $container->setAlias(AbstractThemePathBuilder::class, $theme['theme_path_builder_id']);
        $container->setAlias(AbstractConfigLoader::class, $theme['config_loader_id']);
        $container->setAlias(AbstractAvailableThemeProvider::class, $theme['available_theme_provider']);
    }
}

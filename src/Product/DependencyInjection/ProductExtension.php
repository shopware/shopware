<?php

namespace Shopware\Product\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader;

class ProductExtension extends Extension
{


    /**
     * Loads a specific configuration.
     *
     * @param array $configs              An array of configuration values
     * @param ContainerBuilder $container A ContainerBuilder instance
     *
     * @throws \InvalidArgumentException When provided tag is not defined in this extension
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $this->processConfiguration(new Configuration(), $configs);

        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__));

        $loader->load(__DIR__ . '/services.xml');
        $loader->load(__DIR__ . '/api2-resources.xml');
    }
}
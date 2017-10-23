<?php

namespace Shopware\Traceable;

use Shopware\Storefront\Theme\Theme;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Shopware\Traceable\DependencyInjection\TracerCompilerPass;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;

class Traceable extends Theme
{
    protected $name = 'Traceable';

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new TracerCompilerPass());

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
    }
}
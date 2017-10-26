<?php declare(strict_types=1);

namespace Shopware\Traceable;

use Shopware\Storefront\Theme\Theme;
use Shopware\Traceable\DependencyInjection\CartTracerCompilerPass;
use Shopware\Traceable\DependencyInjection\TracerCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Finder\Finder;

class Traceable extends Theme
{
    protected $name = 'Traceable';

    public function boot()
    {
        parent::boot();

        $directory = $this->container->getParameter('kernel.cache_dir');
        $directory .= '/tracer';

        $finder = new Finder();
        $classes = $finder->in($directory);
        foreach ($classes->getIterator() as $file) {
            require_once (string) $file;
        }
    }

    public function build(ContainerBuilder $container)
    {
        parent::build($container);
        $container->addCompilerPass(new TracerCompilerPass());
        $container->addCompilerPass(new CartTracerCompilerPass());

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');
    }
}

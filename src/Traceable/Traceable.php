<?php declare(strict_types=1);

namespace Shopware\Traceable;

use Shopware\Traceable\DependencyInjection\CartTracerCompilerPass;
use Shopware\Traceable\DependencyInjection\TracerCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class Traceable extends Bundle
{
    protected $name = 'Traceable';

    /**
     * @var ContainerInterface
     */
    protected $container;

    public function boot()
    {
        parent::boot();

        $directory = $this->container->getParameter('kernel.cache_dir');
        $directory .= '/tracer';

        if (!file_exists($directory)) {
            return;
        }

        $finder = new Finder();
        $classes = $finder->in($directory);
        foreach ($classes->getIterator() as $file) {
            try {
                require_once (string)$file;
            } catch (\Exception $e) {

            }
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

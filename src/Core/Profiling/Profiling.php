<?php declare(strict_types=1);

namespace Shopware\Profiling;

use Shopware\Profiling\DependencyInjection\CartTracerCompilerPass;
use Shopware\Profiling\DependencyInjection\TracerCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class Profiling extends Bundle
{
    protected $name = 'Profiling';

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
        /** @var SplFileInfo $file */
        foreach ($classes->getIterator() as $file) {
            $class = str_replace('.php', '', $file->getFilename());
            $full = 'ShopwareTracer\\' . $class;

            if (!class_exists($full)) {
                require_once (string) $file;
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

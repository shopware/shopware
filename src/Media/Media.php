<?php declare(strict_types=1);

namespace Shopware\Media;

use Shopware\Media\DependencyInjection\Compiler\MediaOptimizerCompilerPass;
use Shopware\Media\DependencyInjection\Compiler\MediaStrategyCompilerPass;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class Media extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/DependencyInjection/'));
        $loader->load('services.xml');

        $container->addCompilerPass(new MediaOptimizerCompilerPass());
        $container->addCompilerPass(new MediaStrategyCompilerPass());
    }
}
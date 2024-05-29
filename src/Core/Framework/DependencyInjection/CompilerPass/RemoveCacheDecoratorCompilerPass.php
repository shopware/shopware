<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Adapter\Cache\CacheDecorator;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('core')]
class RemoveCacheDecoratorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (Feature::isActive('cache_rework') || Feature::isActive('v6.7.0.0')) {
            if ($container->hasDefinition(CacheDecorator::class)) {
                $container->removeDefinition(CacheDecorator::class);
            }
        }
    }
}

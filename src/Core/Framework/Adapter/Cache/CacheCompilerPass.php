<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Cache;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('framework')]
class CacheCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $storage = $container->getParameter('shopware.cache.invalidation.delay_options.storage');

        switch ($storage) {
            case 'mysql':
                $container->removeDefinition('shopware.cache.invalidator.storage.redis_adapter');
                $container->removeDefinition('shopware.cache.invalidator.storage.redis');
                break;
            case 'redis':
                if ($container->getParameter('shopware.cache.invalidation.delay_options.connection') === null) {
                    throw AdapterException::missingRequiredParameter('shopware.cache.invalidation.delay_options.connection');
                }

                $container->removeDefinition('shopware.cache.invalidator.storage.mysql');
                break;
        }
    }
}

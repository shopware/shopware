<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Adapter\Cache\ShopwareRedisAdapter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class RedisPrefixCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $ids = ['cache.adapter.redis', 'cache.adapter.redis_tag_aware'];

        foreach ($ids as $id) {
            if (!$container->hasDefinition($id)) {
                continue;
            }

            $definition = $container->getDefinition($id);
            $definition->setClass(ShopwareRedisAdapter::class);
            $definition->addArgument($container->getParameter('shopware.cache.redis_prefix'));
        }
    }
}

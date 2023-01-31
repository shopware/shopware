<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Adapter\Cache\ShopwareRedisAdapter;
use Shopware\Core\Framework\Adapter\Cache\ShopwareRedisTagAwareAdapter;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('core')]
class RedisPrefixCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $ids = [
            'cache.adapter.redis' => ShopwareRedisAdapter::class,
            'cache.adapter.redis_tag_aware' => ShopwareRedisTagAwareAdapter::class,
        ];

        foreach ($ids as $id => $class) {
            if (!$container->hasDefinition($id)) {
                continue;
            }

            $definition = $container->getDefinition($id);
            $definition->setClass($class);
            $definition->addArgument($container->getParameter('shopware.cache.redis_prefix'));
        }
    }
}

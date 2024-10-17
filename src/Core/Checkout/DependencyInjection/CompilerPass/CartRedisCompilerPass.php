<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection\CompilerPass;

use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Checkout\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @deprecated tag:v6.7.0 - reason:becomes-internal - can be renamed to CartStorageCompilerPass
 */
#[Package('core')]
class CartRedisCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // @deprecated tag:v6.7.0 - remove this if block
        if ($container->hasParameter('shopware.cart.redis_url') && $container->getParameter('shopware.cart.redis_url') !== false) {
            Feature::triggerDeprecationOrThrow(
                'v6.7.0.0',
                'Parameter "shopware.cart.redis_url" is deprecated and will be removed. Please use "shopware.cart.storage.config.connection" instead.'
            );

            $container->setParameter('shopware.cart.storage.config.dsn', $container->getParameter('shopware.cart.redis_url'));

            $container->removeDefinition(CartPersister::class);
            $container->setAlias(CartPersister::class, RedisCartPersister::class);

            return;
        }

        // @deprecated tag:v6.7.0 - remove this if block
        if ($container->hasParameter('shopware.cart.redis_url') && $container->getParameter('shopware.cart.redis_url') === false) {
            $container->removeDefinition('shopware.cart.redis');
            $container->removeDefinition(RedisCartPersister::class);

            return;
        }

        $storage = $container->getParameter('shopware.cart.storage.type');

        switch ($storage) {
            case 'mysql':
                $container->removeDefinition('shopware.cart.redis');
                $container->removeDefinition(RedisCartPersister::class);
                break;
            case 'redis':
                if (
                    !$container->hasParameter('shopware.cart.storage.config.dsn') // @deprecated tag:v6.7.0 - remove this line (as config.dsn will be removed)
                    && $container->getParameter('shopware.cart.storage.config.connection') === null
                ) {
                    throw DependencyInjectionException::redisNotConfiguredForCartStorage();
                }

                $container->removeDefinition(CartPersister::class);
                $container->setAlias(CartPersister::class, RedisCartPersister::class);
                break;
        }

        // @deprecated tag:v6.7.0 - remove this if block
        if (!$container->hasParameter('shopware.cart.storage.config.dsn')) {
            // to avoid changing default values in config or using expression language in service configuration
            $container->setParameter('shopware.cart.storage.config.dsn', null);
        }
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\DependencyInjection\CompilerPass;

use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Checkout\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('checkout')]
class CartStorageCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $storage = $container->getParameter('shopware.cart.storage.type');

        switch ($storage) {
            case 'mysql':
                $container->removeDefinition('shopware.cart.redis');
                $container->removeDefinition(RedisCartPersister::class);
                break;
            case 'redis':
                if ($container->getParameter('shopware.cart.storage.config.connection') === null) {
                    throw DependencyInjectionException::redisNotConfiguredForCartStorage();
                }

                $container->removeDefinition(CartPersister::class);
                $container->setAlias(CartPersister::class, RedisCartPersister::class);
                break;
        }
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Redis;

use Shopware\Core\Framework\Adapter\AdapterException;
use Shopware\Core\Framework\Adapter\Cache\RedisConnectionFactory;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[Package('framework')]
class RedisConnectionsCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $connectionServices = $this->prepareConnections($container);

        $connectionProvider = $container->getDefinition(RedisConnectionProvider::class);
        $connectionProvider->replaceArgument(0, ServiceLocatorTagPass::register($container, $connectionServices));
    }

    /**
     * @return array<string, Reference> references to redis connections
     */
    public function prepareConnections(ContainerBuilder $container): array
    {
        if (!$container->hasParameter('shopware.redis.connections')) {
            return [];
        }

        $connections = $container->getParameter('shopware.redis.connections');
        if (!\is_array($connections)) {
            return [];
        }

        $connectionServices = [];
        foreach ($connections as $name => $connection) {
            $dsn = \is_array($connection) ? ($connection['dsn'] ?? null) : null;

            if (!\is_string($dsn)) {
                throw AdapterException::invalidRedisConnectionDsn($name);
            }

            $serviceId = 'shopware.redis.connection.' . $name;
            $definition = $this->createRedisDefinition($dsn);
            $container->setDefinition($serviceId, $definition);
            $connectionServices[$serviceId] = new Reference($serviceId);
        }

        return $connectionServices;
    }

    private function createRedisDefinition(string $dsn): Definition
    {
        $definition = new Definition('Redis');
        $definition
            ->setFactory([new Reference(RedisConnectionFactory::class), 'create'])
            ->setPublic(false)
            ->setArguments([$dsn]);

        // Under the hood, redis connections are created by \Symfony\Component\Cache\Adapter\RedisAdapter::createConnection, which may return
        // different types depending on the redis extension used and dsn provided.
        // On the other side, to implement lazy services, symfony requires a class name to be set, which will be extended by the proxy.
        // That can lead to unexpected behavior, at least for the code that checks the type of the connection using instanceof.
        // If lazy initialization is needed, it's better to inject RedisConnectionProvider into the service and get the connection from it.
        $definition->setLazy(false);

        return $definition;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Monitoring;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class QueueMonitoringAdapterCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $type = $container->getParameter('shopware.queue.monitoring.type');
        $url = $container->getParameter('shopware.queue.monitoring.url');

        $container->setAlias('shopware.queue.monitoring.gateway', 'shopware.queue.monitoring.gateway.' . $type);
        $container->getAlias('shopware.queue.monitoring.gateway')->setPublic(true);

        if ($type !== 'redis') {
            $container->removeDefinition('shopware.queue.monitoring.gateway.redis');
            $container->removeDefinition('shopware.queue.monitoring.redis_adapter');
        }

        if ($type !== 'mysql') {
            $container->removeDefinition('shopware.queue.monitoring.gateway.mysql');
        }

        if ($type !== 'array') {
            $container->removeDefinition('shopware.queue.monitoring.gateway.array');
        }

        if ($type === 'redis' && empty($url)) {
            throw new \RuntimeException('Missing redis url for queue monitoring gateway redis');
        }
    }
}

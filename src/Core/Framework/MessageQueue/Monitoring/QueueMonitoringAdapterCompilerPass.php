<?php declare(strict_types=1);

namespace Shopware\Core\Framework\MessageQueue\Monitoring;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class QueueMonitoringAdapterCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $type = $container->getParameter('shopware.queue.monitoring.type');

        $active = 'shopware.queue.monitoring.gateway.' . $type;
        if (!$container->has($active)) {
            throw new \RuntimeException(sprintf(
                'Can not find monitoring gateway for configured type %s, expected service id %s can not be found',
                $type,
                $active
            ));
        }

        $container->setAlias('shopware.queue.monitoring.gateway', $active);
        $container->getAlias('shopware.queue.monitoring.gateway')->setPublic(true);
    }
}

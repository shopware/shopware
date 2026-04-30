<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * When telemetry metrics are globally disabled, subscribers tagged with `shopware.telemetry.subscriber` are removed to avoid overhead.
 */
#[Package('framework')]
class TelemetrySubscriberCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('shopware.telemetry.metrics.enabled')) {
            return;
        }

        foreach ($container->findTaggedServiceIds('shopware.telemetry.subscriber') as $serviceId => $tags) {
            $container->removeDefinition($serviceId);
        }
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 *
 * When telemetry metrics are globally disabled, services tagged with `shopware.telemetry.subscriber`
 * or `shopware.telemetry.periodic_metric_collector` are removed to avoid overhead.
 */
#[Package('framework')]
class TelemetrySubscriberCompilerPass implements CompilerPassInterface
{
    private const REMOVABLE_TAGS = [
        'shopware.telemetry.subscriber',
        'shopware.telemetry.periodic_metric_collector',
    ];

    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter('shopware.telemetry.metrics.enabled')) {
            return;
        }

        foreach (self::REMOVABLE_TAGS as $tag) {
            foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
                $container->removeDefinition($serviceId);
            }
        }
    }
}

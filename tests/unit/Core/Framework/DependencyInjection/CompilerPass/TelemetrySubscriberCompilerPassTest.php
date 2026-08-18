<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\TelemetrySubscriberCompilerPass;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(TelemetrySubscriberCompilerPass::class)]
class TelemetrySubscriberCompilerPassTest extends TestCase
{
    public function testTelemetrySubscribersAreRemovedWhenDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('shopware.telemetry.metrics.enabled', false);

        $definition = new Definition(\stdClass::class);
        $definition->addTag('kernel.event_subscriber');
        $definition->addTag('shopware.telemetry.subscriber');
        $container->setDefinition('test.telemetry.subscriber', $definition);

        $regularDef = new Definition(\stdClass::class);
        $regularDef->addTag('kernel.event_subscriber');
        $container->setDefinition('test.regular.subscriber', $regularDef);

        $pass = new TelemetrySubscriberCompilerPass();
        $pass->process($container);

        static::assertFalse($container->hasDefinition('test.telemetry.subscriber'));
        static::assertTrue($container->hasDefinition('test.regular.subscriber'));
        static::assertTrue($container->getDefinition('test.regular.subscriber')->hasTag('kernel.event_subscriber'));
    }

    public function testPeriodicMetricCollectorsAreRemovedWhenDisabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('shopware.telemetry.metrics.enabled', false);

        $collectorDef = new Definition(\stdClass::class);
        $collectorDef->addTag('shopware.telemetry.periodic_metric_collector');
        $container->setDefinition('test.telemetry.collector', $collectorDef);

        $unrelatedDef = new Definition(\stdClass::class);
        $container->setDefinition('test.unrelated.service', $unrelatedDef);

        $pass = new TelemetrySubscriberCompilerPass();
        $pass->process($container);

        static::assertFalse($container->hasDefinition('test.telemetry.collector'));
        static::assertTrue($container->hasDefinition('test.unrelated.service'));
    }

    public function testSubscribersAndCollectorsAreKeptWhenEnabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('shopware.telemetry.metrics.enabled', true);

        $definition = new Definition(\stdClass::class);
        $definition->addTag('kernel.event_subscriber');
        $definition->addTag('shopware.telemetry.subscriber');
        $container->setDefinition('test.telemetry.subscriber', $definition);

        $collectorDef = new Definition(\stdClass::class);
        $collectorDef->addTag('shopware.telemetry.periodic_metric_collector');
        $container->setDefinition('test.telemetry.collector', $collectorDef);

        $pass = new TelemetrySubscriberCompilerPass();
        $pass->process($container);

        static::assertTrue($container->hasDefinition('test.telemetry.subscriber'));
        static::assertTrue($container->getDefinition('test.telemetry.subscriber')->hasTag('kernel.event_subscriber'));
        static::assertTrue($container->hasDefinition('test.telemetry.collector'));
        static::assertTrue($container->getDefinition('test.telemetry.collector')->hasTag('shopware.telemetry.periodic_metric_collector'));
    }
}

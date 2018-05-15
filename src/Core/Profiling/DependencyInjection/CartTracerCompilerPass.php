<?php declare(strict_types=1);

namespace Shopware\Profiling\DependencyInjection;

use Shopware\Checkout\Cart\Cart\CircularCartCalculation;
use Shopware\Profiling\Cart\CartCollectorTracer;
use Shopware\Profiling\Cart\CircularCartCalculationTracer;
use Shopware\Profiling\Cart\ProcessorTracer;
use Shopware\Profiling\Cart\TracedCartActions;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class CartTracerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $services = $container->findTaggedServiceIds('cart.processor');

        foreach ($services as $id => $tags) {
            $this->decorateService($container, $id, ProcessorTracer::class);
        }

        $services = $container->findTaggedServiceIds('cart.collector');
        foreach ($services as $id => $tags) {
            $this->decorateService($container, $id, CartCollectorTracer::class);
        }

        $definition = new Definition(
            CircularCartCalculationTracer::class,
            [
                new Reference('Shopware\Checkout\Cart\Cart\CircularCartCalculation.tracer.inner'),
                new Reference(TracedCartActions::class),
            ]
        );
        $definition->setDecoratedService(CircularCartCalculation::class);
        $container->setDefinition('Shopware\Checkout\Cart\Cart\CircularCartCalculation.tracer', $definition);
    }

    protected function decorateService(ContainerBuilder $container, string $serviceId, string $class): void
    {
        $new = new Definition($class, [
            new Reference($serviceId . '.tracer.inner'),
            new Reference(TracedCartActions::class),
        ]);

        $new->setDecoratedService($serviceId);
        $container->setDefinition($serviceId . '.tracer', $new);
    }
}

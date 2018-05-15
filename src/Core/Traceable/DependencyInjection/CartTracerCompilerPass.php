<?php declare(strict_types=1);

namespace Shopware\Traceable\DependencyInjection;

use Shopware\Checkout\Cart\Cart\CircularCartCalculation;
use Shopware\Traceable\Cart\CartCollectorTracer;
use Shopware\Traceable\Cart\CircularCartCalculationTracer;
use Shopware\Traceable\Cart\ProcessorTracer;
use Shopware\Traceable\Cart\TracedCartActions;
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

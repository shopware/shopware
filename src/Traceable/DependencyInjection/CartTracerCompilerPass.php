<?php

namespace Shopware\Traceable\DependencyInjection;

use Shopware\Traceable\Cart\CartCalculatorTracer;
use Shopware\Traceable\Cart\CollectorTracer;
use Shopware\Traceable\Cart\ProcessorTracer;
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
            $this->replaceProcessor($container, $id);
        }

        $services = $container->findTaggedServiceIds('cart.collector');
        foreach ($services as $id => $tags) {
            $this->replaceCollector($container, $id);
        }

        $definition = new Definition(
            CartCalculatorTracer::class,
            [
                new Reference('cart.calculator.tracer.inner'),
                new Reference('shopware.traceable.traced_cart_actions'),
            ]
        );
        $definition->setDecoratedService('cart.calculator');
        $container->setDefinition('cart.calculator.tracer', $definition);
    }

    protected function replaceProcessor(ContainerBuilder $container, string $serviceId): void
    {
        $new = new Definition(ProcessorTracer::class, [
            new Reference($serviceId . '.tracer.inner'),
            new Reference('shopware.traceable.traced_cart_actions'),
        ]);

        $new->setDecoratedService($serviceId);
        $container->setDefinition($serviceId . '.tracer', $new);
    }

    private function replaceCollector(ContainerBuilder $container, string $serviceId)
    {
        $new = new Definition(CollectorTracer::class, [
            new Reference($serviceId . '.tracer.inner'),
            new Reference('shopware.traceable.traced_cart_actions'),
        ]);

        $new->setDecoratedService($serviceId);
        $container->setDefinition($serviceId . '.tracer', $new);
    }
}
<?php declare(strict_types=1);

namespace Shopware\Traceable\DependencyInjection;

use Shopware\Cart\Cart\CartCalculator;
use Shopware\Traceable\Cart\CartCalculatorTracer;
use Shopware\Traceable\Cart\CollectorTracer;
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
            $this->decorateService($container, $id, CollectorTracer::class);
        }

        $definition = new Definition(
            CartCalculatorTracer::class,
            [
                new Reference('Shopware\Cart\Cart\CartCalculator.tracer.inner'),
                new Reference(TracedCartActions::class),
            ]
        );
        $definition->setDecoratedService(CartCalculator::class);
        $container->setDefinition('Shopware\Cart\Cart\CartCalculator.tracer', $definition);
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

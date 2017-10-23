<?php

namespace Shopware\Traceable\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

class TracerCompilerPass implements CompilerPassInterface
{
    /**
     * @var TracerGenerator
     */
    private $generator;

    public function process(ContainerBuilder $container)
    {
        $this->generator = new TracerGenerator();

        $services = $container->findTaggedServiceIds('shopware.traceable');
        foreach ($services as $id => $tags) {
            $this->replaceService($container, $id);
        }

        $services = $container->findTaggedServiceIds('cart.collector');
        foreach ($services as $id => $tags) {
            $this->replaceService($container, $id);
        }

        $services = $container->getServiceIds();
        $services = array_filter(
            $services,
            function (string $service) {
                return (
                    $this->isRepository($service)
                    || $this->isLoader($service)
                    || $this->isSearcher($service)
                    || $this->isProcessor($service)
                );
            }
        );
        
        foreach ($services as $id) {
            $this->replaceService($container, $id);
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param string $serviceId
     */
    protected function replaceService(ContainerBuilder $container, string $serviceId): void
    {
        $definition = $container->getDefinition($serviceId);

        $className = $this->generator->createTracer($definition->getClass(), $serviceId);

        $new = new Definition(
            $className, [
            new Reference($serviceId.'.inner'),
            new Reference('debug.stopwatch'),
        ]);

        $container->setDefinition($serviceId.'.inner', $definition);
        $container->setDefinition($serviceId, $new);
    }

    private function isRepository(string $service): bool
    {
        return strpos($service, '.repository') > 0;
    }

    private function isLoader(string $service): bool
    {
        return strpos($service, '._loader') > 0;
    }

    private function isSearcher(string $service): bool
    {
        return strpos($service, '.searcher') > 0;
    }

    private function isProcessor(string $service): bool
    {
        return strpos($service, '.collector') > 0 && strpos($service, 'cart') > 0 ;
    }
}
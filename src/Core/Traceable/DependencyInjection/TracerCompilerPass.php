<?php declare(strict_types=1);

namespace Shopware\Traceable\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Filesystem\Filesystem;

class TracerCompilerPass implements CompilerPassInterface
{
    /**
     * @var TracerGenerator
     */
    private $generator;

    public function process(ContainerBuilder $container)
    {
        $directory = $container->getParameter('kernel.cache_dir');
        $directory .= '/tracer';
        if (!file_exists($directory)) {
            $system = new Filesystem();
            $system->mkdir($directory);
        }

        $this->generator = new TracerGenerator($directory);

        $services = $container->findTaggedServiceIds('shopware.traceable');
        foreach ($services as $id => $tags) {
            $this->replaceService($container, $id, $tags);
        }

        $services = $container->findTaggedServiceIds('cart.collector');
        foreach ($services as $id => $tags) {
            $this->replaceService($container, $id);
        }

        $services = $container->findTaggedServiceIds('cart.processor');
        foreach ($services as $id => $tags) {
            $this->replaceService($container, $id);
        }
    }

    /**
     * @param ContainerBuilder $container
     * @param string           $serviceId
     */
    protected function replaceService(ContainerBuilder $container, string $serviceId, array $tags = []): void
    {
        $definition = $container->getDefinition($serviceId);

        $label = $serviceId;
        foreach ($tags as $tag) {
            if (array_key_exists('label', $tag)) {
                $label = $tag['label'];
            }
        }

        $className = $this->generator->createTracer($definition->getClass(), $label);

        $new = new Definition(
            $className, [
            new Reference($serviceId . '.inner'),
            new Reference('debug.stopwatch'),
        ]);

        $container->setDefinition($serviceId . '.inner', $definition);
        $container->setDefinition($serviceId, $new);
    }
}

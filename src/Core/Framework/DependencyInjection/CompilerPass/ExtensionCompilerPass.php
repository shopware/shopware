<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class ExtensionCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $this->replaceArgumentWithTaggedServices($container, 'shopware.extension.registry', 'shopware.extension', 0);

        $services = $container->findTaggedServiceIds('shopware.extension');

        foreach ($services as $id => $service) {
            $definition = $container->getDefinition($id);
            if ($definition->hasTag('kernel.event_subscriber')) {
                continue;
            }
            $definition->addTag('kernel.event_subscriber');
        }
    }

    private function replaceArgumentWithTaggedServices(ContainerBuilder $container, string $serviceName, string $tagName, int $argumentIndex): void
    {
        if (!$container->hasDefinition($serviceName)) {
            return;
        }

        $taggedServices = $this->findAndSortTaggedServices($tagName, $container);

        if (empty($taggedServices)) {
            return;
        }

        $definition = $container->getDefinition($serviceName);

        $grouped = [];
        foreach ($taggedServices as $service) {
            $bundle = $service['bundle'];
            $grouped[$bundle][] = $service['reference'];
        }

        $definition->replaceArgument($argumentIndex, $grouped);
    }

    /**
     * Finds all services with the given tag name and order them by their priority.
     *
     * @return array[]
     */
    private function findAndSortTaggedServices(string $tagName, ContainerBuilder $container): array
    {
        $services = $container->findTaggedServiceIds($tagName);

        $queue = [];

        foreach ($services as $serviceId => $tags) {
            foreach ($tags as $attributes) {
                $priority = $attributes['priority'] ?? 0;
                $queue[] = [
                    'reference' => new Reference($serviceId),
                    'bundle' => $attributes['bundle'],
                    'priority' => $priority,
                ];
            }
        }

        usort($queue, function (array $a, array $b) {
            return $a['priority'] <=> $b['priority'];
        });

        return $queue;
    }
}

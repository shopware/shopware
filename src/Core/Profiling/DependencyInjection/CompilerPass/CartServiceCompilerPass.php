<?php declare(strict_types=1);

namespace Shopware\Core\Profiling\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Profiling\Subscriber\CartDataCollectorSubscriber;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * @internal
 */
#[Package('framework')]
class CartServiceCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(CartDataCollectorSubscriber::class)) {
            return;
        }

        $definition = $container->getDefinition(CartDataCollectorSubscriber::class);
        $definition->setArgument(1, $this->processTaggedServices($container, 'shopware.cart.collector'));
        $definition->setArgument(2, $this->processTaggedServices($container, 'shopware.cart.processor'));
    }

    /**
     * @return array<string, array{serviceId: string, priority: int, decoratedBy: list<array{serviceId: string, priority: int}>}>
     */
    private function processTaggedServices(ContainerBuilder $container, string $tag): array
    {
        $services = [];
        foreach ($container->findTaggedServiceIds($tag) as $serviceId => $tags) {
            foreach ($tags as $tag) {
                $priority = (int) ($tag['priority'] ?? 0);
                $services[$serviceId] = [
                    'serviceId' => $serviceId,
                    'priority' => $priority,
                    'decoratedBy' => [],
                ];
                break;
            }
        }

        $this->extractDecorationInfo($container, $services);

        // Sort collectors by priority (higher number = higher priority)
        uasort($services, static function ($a, $b) {
            return $b['priority'] <=> $a['priority'];
        });

        return $services;
    }

    /**
     * @param array<string, array{serviceId: string, priority: int, decoratedBy: list<array{serviceId: string, priority: int}>}> $services
     */
    private function extractDecorationInfo(ContainerBuilder $container, array &$services): void
    {
        $decoratedByIndex = [];
        foreach ($container->getDefinitions() as $id => $definition) {
            $decorated = $definition->getDecoratedService();
            if ($decorated === null) {
                continue;
            }

            // Format: [decorated service ID, decoration inner name, decoration priority]
            $decoratedServiceId = $decorated[0];
            $decorationPriority = (int) ($decorated[2] ?? 0);

            $decoratedByIndex[$decoratedServiceId][] = [
                'serviceId' => $id,
                'priority' => $decorationPriority,
            ];
        }

        foreach ($services as $serviceId => $info) {
            $decorators = $decoratedByIndex[$serviceId] ?? [];
            usort($decorators, static function ($a, $b) {
                return $b['priority'] <=> $a['priority'];
            });

            $services[$serviceId]['decoratedBy'] = $decorators;
        }
    }
}

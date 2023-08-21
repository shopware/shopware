<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[Package('core')]
abstract class RemoveEventListener
{
    /**
     * @param array<string[]> $remove
     */
    public static function remove(ContainerBuilder $builder, string $serviceId, array $remove): void
    {
        if (!$builder->hasDefinition($serviceId)) {
            return;
        }

        $definition = $builder->getDefinition($serviceId);

        $listeners = $definition->getTag('kernel.event_listener');

        $definition->clearTag('kernel.event_listener');

        $map = \array_map(function (array $item) {
            return \implode('::', $item);
        }, $remove);

        foreach ($listeners as $listener) {
            $key = $listener['event'] . '::' . $listener['method'];

            if (!\in_array($key, $map, true)) {
                $definition->addTag('kernel.event_listener', $listener);
            }
        }
    }
}

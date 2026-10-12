<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskExecutor;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Injects the {@see ScheduledTaskExecutor} into every {@see ScheduledTaskHandler} via a `setScheduledTaskExecutor()`
 * method call, so the execution orchestration lives in a dedicated service instead of the abstract handler.
 *
 * @internal
 */
#[Package('framework')]
class ScheduledTaskExecutorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(ScheduledTaskExecutor::class)) {
            return;
        }

        $executor = new Reference(ScheduledTaskExecutor::class);

        foreach (array_keys($container->findTaggedServiceIds('messenger.message_handler')) as $serviceId) {
            $definition = $container->findDefinition($serviceId);

            $class = $definition->getClass() ?? $serviceId;

            if (!is_subclass_of($class, ScheduledTaskHandler::class)) {
                continue;
            }

            $definition->addMethodCall('setScheduledTaskExecutor', [$executor]);
        }
    }
}

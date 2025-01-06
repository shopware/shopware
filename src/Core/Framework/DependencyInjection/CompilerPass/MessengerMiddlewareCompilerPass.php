<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Middleware\RoutingOverwriteMiddleware;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[Package('core')]
class MessengerMiddlewareCompilerPass implements CompilerPassInterface
{
    use CompilerPassConfigTrait;

    public function process(ContainerBuilder $container): void
    {
        $messageBus = $container->getDefinition('messenger.bus.default');

        $middlewares = $messageBus->getArgument(0);

        \assert($middlewares instanceof IteratorArgument);

        $messageBus->replaceArgument(
            0,
            new IteratorArgument([
                new Reference(RoutingOverwriteMiddleware::class),
                ...$middlewares->getValues(),
            ])
        );

        // @deprecated tag:v6.7.0 - remove all code below, overwrites are now handled via shopware.messenger.routing_overwrites
        $config = $this->getConfig($container, 'framework');

        if (!\array_key_exists('messenger', $config)) {
            return;
        }

        $mapped = [];
        foreach ($config['messenger']['routing'] as $message => $transports) {
            if (!\array_key_exists('senders', $transports)) {
                continue;
            }
            $mapped[$message] = array_shift($transports['senders']);
        }

        $container
            ->getDefinition(RoutingOverwriteMiddleware::class)
            ->replaceArgument(1, $mapped);
    }
}

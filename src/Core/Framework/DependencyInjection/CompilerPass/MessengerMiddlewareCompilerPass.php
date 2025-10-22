<?php declare(strict_types=1);

namespace Shopware\Core\Framework\DependencyInjection\CompilerPass;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\MessageQueue\Middleware\RoutingOverwriteMiddleware;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[Package('framework')]
class MessengerMiddlewareCompilerPass implements CompilerPassInterface
{
    use CompilerPassConfigTrait;

    public function process(ContainerBuilder $container): void
    {
        $messageBus = $container->getDefinition('messenger.bus.default');

        $middlewares = $messageBus->getArgument(0);

        if ($middlewares instanceof IteratorArgument) {
            $messageBus->replaceArgument(
                0,
                new IteratorArgument([
                    new Reference(RoutingOverwriteMiddleware::class),
                    ...$middlewares->getValues(),
                ])
            );
        } else {
            $messageBus->replaceArgument(
                0,
                new IteratorArgument([
                    new Reference(RoutingOverwriteMiddleware::class),
                ])
            );
        }
    }
}

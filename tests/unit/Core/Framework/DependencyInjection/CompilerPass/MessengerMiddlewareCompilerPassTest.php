<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\MessengerMiddlewareCompilerPass;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\MessageQueue\Middleware\RoutingOverwriteMiddleware;
use Symfony\Component\DependencyInjection\Argument\IteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(MessengerMiddlewareCompilerPass::class)]
class MessengerMiddlewareCompilerPassTest extends TestCase
{
    public function testMiddlewareIsRegistered(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('messenger.default_transport_name', 'test');
        $container->setParameter('kernel.debug', true);

        $container->addCompilerPass(new MessengerMiddlewareCompilerPass());

        $busDefinition = new Definition(MessageBusInterface::class);
        $busDefinition->setArguments([new IteratorArgument([]), []]);

        $middlewareDefinition = new Definition(RoutingOverwriteMiddleware::class);
        $middlewareDefinition->setArguments([[], []]);

        $container->setDefinitions([
            'messenger.bus.default' => $busDefinition,
            RoutingOverwriteMiddleware::class => $middlewareDefinition,
        ]);

        // disable removing passes because the alias will not be used
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        $container->compile();

        $argument = $busDefinition->getArgument(0);
        static::assertInstanceOf(IteratorArgument::class, $argument);
        static::assertSame(RoutingOverwriteMiddleware::class, (string) $argument->getValues()[0]);
    }

    public function testRoutingIsApplied(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $config = [
            'routing' => [
                ProductIndexingMessage::class => 'foo',
            ],
        ];

        $container = new class($config) extends ContainerBuilder {
            /**
             * @param array<string, array<class-string, string>> $config
             */
            public function __construct(private array $config)
            {
                parent::__construct();
            }

            public function getExtensionConfig(string $name): array
            {
                return [
                    ['messenger' => $this->config],
                ];
            }
        };

        $container->setParameter('messenger.default_transport_name', 'test');
        $container->setParameter('kernel.debug', true);

        $busDefinition = new Definition(MessageBusInterface::class);
        $busDefinition->setArguments([new IteratorArgument([]), []]);

        $middlewareDefinition = new Definition(RoutingOverwriteMiddleware::class);
        $middlewareDefinition->setArguments([[], []]);

        $container->setDefinitions([
            'messenger.bus.default' => $busDefinition,
            RoutingOverwriteMiddleware::class => $middlewareDefinition,
        ]);

        $container->addCompilerPass(new MessengerMiddlewareCompilerPass());

        // disable removing passes because the alias will not be used
        $container->getCompilerPassConfig()->setRemovingPasses([]);
        $container->getCompilerPassConfig()->setAfterRemovingPasses([]);

        $container->compile(true);

        static::assertEquals($middlewareDefinition->getArgument(1), $config['routing']);
    }
}

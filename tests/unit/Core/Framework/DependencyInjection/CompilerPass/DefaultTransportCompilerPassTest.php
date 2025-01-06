<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\DefaultTransportCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * @internal
 */
#[CoversClass(DefaultTransportCompilerPass::class)]
class DefaultTransportCompilerPassTest extends TestCase
{
    public function testAliasIsRegistered(): void
    {
        $container = new ContainerBuilder();

        $container->setParameter('messenger.default_transport_name', 'test');
        $container->setParameter('kernel.debug', true);

        $container->addCompilerPass(new DefaultTransportCompilerPass());

        $definition = new Definition(MessageBusInterface::class);
        $definition->setArguments([null, []]);
        $container->setDefinition('messenger.bus.shopware', $definition);

        // disable removing passes because the alias will not be used
        $container->getCompilerPassConfig()->setRemovingPasses([]);

        $container->compile(true);

        static::assertSame('messenger.transport.test', (string) $container->getAlias('messenger.default_transport'));
    }
}

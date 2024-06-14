<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Framework\Adapter\Messenger\MessageBus;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\DefaultTransportCompilerPass;
use Shopware\Core\Framework\Feature;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(DefaultTransportCompilerPass::class)]
class DefaultTransportCompilerPassTest extends TestCase
{
    public function testCompiler(): void
    {
        Feature::skipTestIfActive('v6.7.0.0', $this);

        $config = [
            'routing' => [
                ProductIndexingMessage::class => 'foo',
            ],
        ];
        $container = new StubContainer($config);

        $container->setParameter('messenger.default_transport_name', 'test');
        $container->setParameter('kernel.debug', true);

        $container->addCompilerPass(new DefaultTransportCompilerPass());

        $definition = new Definition(MessageBus::class);
        $definition->setArguments([null, []]);
        $container->setDefinition('messenger.bus.shopware', $definition);

        $container->compile(true);

        static::assertEquals($definition->getArgument(1), $config['routing']);
    }
}

/**
 * @internal
 */
class StubContainer extends ContainerBuilder
{
    /**
     * @param array<string, mixed> $config
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
}

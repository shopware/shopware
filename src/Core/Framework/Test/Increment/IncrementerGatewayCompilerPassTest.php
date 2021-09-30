<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Test\Increment;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\AbstractIncrementer;
use Shopware\Core\Framework\Increment\ArrayIncrementer;
use Shopware\Core\Framework\Increment\IncrementerGatewayCompilerPass;
use Shopware\Core\Framework\Increment\MySQLIncrementer;
use Shopware\Core\Framework\Increment\RedisIncrementer;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class IncrementerGatewayCompilerPassTest extends TestCase
{
    use IntegrationTestBehaviour;

    public function testProcess(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('shopware.increment', [
            'user_activity' => [
                'type' => 'mysql',
            ],
            'message_queue' => [
                'type' => 'redis',
                'config' => ['url' => 'redis://test'],
            ],
            'another_pool' => [
                'type' => 'array',
            ],
        ]);

        $container->register('shopware.increment.gateway.array', ArrayIncrementer::class)
            ->addArgument('');

        $container->register('shopware.increment.gateway.mysql', MySQLIncrementer::class)
            ->addArgument('')
            ->addArgument($this->getContainer()->get(Connection::class));

        $entityCompilerPass = new IncrementerGatewayCompilerPass();
        $entityCompilerPass->process($container);

        // user_activity pool is registered
        static::assertNotNull($container->hasDefinition('shopware.increment.user_activity.gateway.mysql'));
        $definition = $container->getDefinition('shopware.increment.user_activity.gateway.mysql');
        static::assertEquals(MySQLIncrementer::class, $definition->getClass());
        static::assertTrue($definition->hasTag('shopware.increment.gateway'));

        // message_queue pool is registered
        static::assertNotNull($container->hasDefinition('shopware.increment.message_queue.redis_adapter'));
        static::assertNotNull($container->hasDefinition('shopware.increment.message_queue.gateway.redis'));
        $definition = $container->getDefinition('shopware.increment.message_queue.gateway.redis');
        static::assertEquals(RedisIncrementer::class, $definition->getClass());
        static::assertTrue($definition->hasTag('shopware.increment.gateway'));

        // another_pool is registered
        static::assertNotNull($container->hasDefinition('shopware.increment.message_queue.gateway.redis'));
        $definition = $container->getDefinition('shopware.increment.message_queue.gateway.redis');
        static::assertEquals(RedisIncrementer::class, $definition->getClass());
        static::assertTrue($definition->hasTag('shopware.increment.gateway'));
    }

    public function testCustomPoolGateway(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('shopware.increment', ['custom_pool' => ['type' => 'custom_type']]);

        $customGateway = new class() extends AbstractIncrementer {
            public function getDecorated(): AbstractIncrementer
            {
                throw new DecorationPatternException(static::class);
            }

            public function decrement(string $cluster, string $key): void
            {
            }

            public function increment(string $cluster, string $key): void
            {
            }

            public function list(string $cluster, int $limit = 5, int $offset = 0): array
            {
                return [];
            }

            public function reset(string $cluster, ?string $key = null): void
            {
            }

            public function getPool(): string
            {
                return 'custom-pool';
            }
        };

        $container->setDefinition('shopware.increment.custom_pool.gateway.custom_type', new Definition(\get_class($customGateway)));

        $entityCompilerPass = new IncrementerGatewayCompilerPass();
        $entityCompilerPass->process($container);

        // custom_pool pool is registered
        static::assertNotNull($container->hasDefinition('shopware.increment.custom_pool.gateway.custom_type'));
        $definition = $container->getDefinition('shopware.increment.custom_pool.gateway.custom_type');
        static::assertEquals(\get_class($customGateway), $definition->getClass());
        static::assertTrue($definition->hasTag('shopware.increment.gateway'));
    }

    public function testInvalidCustomPoolGateway(): void
    {
        static::expectException(\RuntimeException::class);
        $container = new ContainerBuilder();
        $container->setParameter('shopware.increment', ['custom_pool' => []]);
        $container->setParameter('shopware.increment.custom_pool.type', 'custom_type');

        $customGateway = new class() {
            public function getPool(): string
            {
                return 'custom-pool';
            }
        };

        $container->setDefinition('shopware.increment.custom_pool.gateway.custom_type', new Definition(\get_class($customGateway)));

        $entityCompilerPass = new IncrementerGatewayCompilerPass();
        $entityCompilerPass->process($container);

        // custom_pool pool is registered
        static::assertNotNull($container->hasDefinition('shopware.increment.custom_pool.gateway.custom_type'));
        $definition = $container->getDefinition('shopware.increment.custom_pool.gateway.custom_type');
        static::assertEquals(\get_class($customGateway), $definition->getClass());
        static::assertTrue($definition->hasTag('shopware.increment.gateway'));
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\DependencyInjection\CompilerPass\NumberRangeIncrementerCompilerPass;
use Shopware\Core\System\DependencyInjection\DependencyInjectionException;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementRedisStorage;
use Shopware\Core\System\NumberRange\ValueGenerator\Pattern\IncrementStorage\IncrementSqlStorage;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(NumberRangeIncrementerCompilerPass::class)]
class RedisNumberRangeIncrementerCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->addDefinitions([
            IncrementRedisStorage::class => new Definition(),
            'shopware.number_range.redis' => new Definition(),
            IncrementSqlStorage::class => new Definition(),
        ]);
        $this->container->setParameter('shopware.number_range.config.connection', null);
    }

    public function testProcessSql(): void
    {
        $container = $this->container;
        $container->setParameter('shopware.number_range.increment_storage', 'mysql');

        $compilerPass = new NumberRangeIncrementerCompilerPass();
        $compilerPass->process($container);

        static::assertFalse($container->hasDefinition(IncrementRedisStorage::class));
        static::assertFalse($container->hasDefinition('shopware.number_range.redis'));
        static::assertTrue($container->hasDefinition(IncrementSqlStorage::class));
    }

    public function testProcessRedis(): void
    {
        $container = $this->container;
        $container->setParameter('shopware.number_range.increment_storage', 'redis');
        $container->setParameter('shopware.number_range.config.connection', 'my_connection');

        $compilerPass = new NumberRangeIncrementerCompilerPass();
        $compilerPass->process($container);

        static::assertTrue($container->hasDefinition(IncrementRedisStorage::class));
        static::assertTrue($container->hasDefinition('shopware.number_range.redis'));
        static::assertFalse($container->hasDefinition(IncrementSqlStorage::class));
    }

    public function testProcessRedisNoConnection(): void
    {
        $container = $this->container;
        $container->setParameter('shopware.number_range.increment_storage', 'redis');

        self::expectException(DependencyInjectionException::class); // redis connection is not configured
        $compilerPass = new NumberRangeIncrementerCompilerPass();
        $compilerPass->process($container);
    }
}

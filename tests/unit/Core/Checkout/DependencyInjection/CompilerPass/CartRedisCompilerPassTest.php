<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Checkout\DependencyInjection\CompilerPass\CartStorageCompilerPass;
use Shopware\Core\Checkout\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartStorageCompilerPass::class)]
class CartRedisCompilerPassTest extends TestCase
{
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->addDefinitions([
            'shopware.cart.redis' => new Definition(),
            RedisCartPersister::class => new Definition(),
            CartPersister::class => new Definition(),
        ]);
    }

    public function testCompilerPassMysqlStorage(): void
    {
        $this->container->setParameter('shopware.cart.storage.type', 'mysql');

        $compilerPass = new CartStorageCompilerPass();
        $compilerPass->process($this->container);

        static::assertTrue($this->container->hasDefinition(CartPersister::class));
        static::assertFalse($this->container->hasDefinition('shopware.cart.redis'));
        static::assertFalse($this->container->hasDefinition(RedisCartPersister::class));
    }

    public function testCompilerPassRedisStorageConnectionName(): void
    {
        $this->container->setParameter('shopware.cart.storage.type', 'redis');
        $this->container->setParameter('shopware.cart.storage.config.connection', 'persistent');

        $compilerPass = new CartStorageCompilerPass();
        $compilerPass->process($this->container);

        static::assertTrue($this->container->hasDefinition(RedisCartPersister::class));
        static::assertFalse($this->container->hasDefinition(CartPersister::class));
    }

    public function testCompilerPassRedisStorageWithoutDsn(): void
    {
        $this->container->setParameter('shopware.cart.storage.config.connection', null); // equal to default in config
        $this->container->setParameter('shopware.cart.storage.type', 'redis');
        $this->container->getParameterBag()->remove('shopware.cart.storage.config.dsn');

        $compilerPass = new CartStorageCompilerPass();

        $this->expectExceptionMessage('Parameter "shopware.cart.storage.config.connection" is required for redis storage');
        $this->expectException(DependencyInjectionException::class);

        $compilerPass->process($this->container);
    }
}

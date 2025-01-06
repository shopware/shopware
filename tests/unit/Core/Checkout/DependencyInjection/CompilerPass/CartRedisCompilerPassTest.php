<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\CartPersister;
use Shopware\Core\Checkout\Cart\RedisCartPersister;
use Shopware\Core\Checkout\DependencyInjection\CompilerPass\CartRedisCompilerPass;
use Shopware\Core\Checkout\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(CartRedisCompilerPass::class)]
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

        $compilerPass = new CartRedisCompilerPass();
        $compilerPass->process($this->container);

        static::assertTrue($this->container->hasDefinition(CartPersister::class));
        static::assertFalse($this->container->hasDefinition('shopware.cart.redis'));
        static::assertFalse($this->container->hasDefinition(RedisCartPersister::class));
    }

    /**
     * @deprecated tag:v6.7.0 - Remove in 6.7
     */
    public function testCompilerPassRedisStorageDsn(): void
    {
        $this->container->setParameter('shopware.cart.storage.type', 'redis');
        $this->container->setParameter('shopware.cart.storage.config.dsn', 'redis://localhost:6379');

        $compilerPass = new CartRedisCompilerPass();
        $compilerPass->process($this->container);

        static::assertTrue($this->container->hasDefinition(RedisCartPersister::class));
        static::assertFalse($this->container->hasDefinition(CartPersister::class));
    }

    public function testCompilerPassRedisStorageConnectionName(): void
    {
        $this->container->setParameter('shopware.cart.storage.type', 'redis');
        $this->container->setParameter('shopware.cart.storage.config.connection', 'persistent');

        $compilerPass = new CartRedisCompilerPass();
        $compilerPass->process($this->container);

        static::assertTrue($this->container->hasDefinition(RedisCartPersister::class));
        static::assertFalse($this->container->hasDefinition(CartPersister::class));
    }

    /**
     * @deprecated tag:v6.7.0 - Remove in 6.7
     */
    #[DisabledFeatures(['v6.7.0.0'])]
    public function testCompilerPassRedisUrl(): void
    {
        $this->container->setParameter('shopware.cart.redis_url', 'redis://localhost:6379');

        $compilerPass = new CartRedisCompilerPass();
        $compilerPass->process($this->container);

        static::assertTrue($this->container->hasParameter('shopware.cart.storage.config.connection'));
        static::assertTrue($this->container->hasDefinition(RedisCartPersister::class));
        static::assertFalse($this->container->hasDefinition(CartPersister::class));
    }

    public function testCompilerPassRedisStorageWithoutDsn(): void
    {
        $this->container->setParameter('shopware.cart.storage.config.connection', null); // equal to default in config
        $this->container->setParameter('shopware.cart.storage.type', 'redis');
        $this->container->getParameterBag()->remove('shopware.cart.storage.config.dsn');

        $compilerPass = new CartRedisCompilerPass();

        // @deprecated tag:v6.7.0 - update exception message to reflect removed shopware.cart.storage.config.dsn parameter
        $this->expectExceptionMessage('Parameter "shopware.cart.storage.config.dsn" or "shopware.cart.storage.config.connection" is required for redis storage');
        $this->expectException(DependencyInjectionException::class);

        $compilerPass->process($this->container);
    }
}

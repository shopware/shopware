<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\RedisPrefixCompilerPass;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 */
#[CoversClass(RedisPrefixCompilerPass::class)]
class RedisPrefixCompilerPassTest extends TestCase
{
    public function testProcess(): void
    {
        $container = new ContainerBuilder();

        $definition = new Definition(RedisTagAwareAdapter::class);
        $definition->setArguments(['', 'foo']);

        $container->setDefinition('foo', $definition);

        $pass = new RedisPrefixCompilerPass();
        $pass->process($container);

        static::assertEquals('%shopware.cache.redis_prefix%foo', $definition->getArgument(1));
    }
}

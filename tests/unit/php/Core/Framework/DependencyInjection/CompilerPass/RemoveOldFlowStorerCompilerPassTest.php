<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Flow\Dispatching\Storer\ShopNameStorer;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\RemoveOldFlowStorerCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @internal
 *
 * @covers \Shopware\Core\Framework\DependencyInjection\CompilerPass\RemoveOldFlowStorerCompilerPass
 */
class RemoveOldFlowStorerCompilerPassTest extends TestCase
{
    public function testProcess(): void
    {
        $builder = new ContainerBuilder();

        $definition = new Definition(\stdClass::class);
        $definition->addTag('flow.storer');
        $definition->addTag('not-affected');

        $builder->setDefinition('not-affected', $definition);

        $definition = new Definition(ShopNameStorer::class);
        $definition->addTag('flow.storer');
        $definition->addTag('not-affected');
        $builder->setDefinition(ShopNameStorer::class, $definition);

        $compilerPass = new RemoveOldFlowStorerCompilerPass();
        $compilerPass->process($builder);

        static::assertTrue($builder->hasDefinition('not-affected'));
        static::assertTrue($builder->hasDefinition(ShopNameStorer::class));

        $definition = $builder->getDefinition(ShopNameStorer::class);
        static::assertTrue($definition->hasTag('not-affected'));
        static::assertFalse($definition->hasTag('flow.storer'));

        $definition = $builder->getDefinition('not-affected');
        static::assertTrue($definition->hasTag('not-affected'));
        static::assertTrue($definition->hasTag('flow.storer'));
    }
}

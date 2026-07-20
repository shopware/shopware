<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Adapter\Session\SessionFactory;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\OverwriteSessionFactoryCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Session\SessionFactory as SymfonySessionFactory;

/**
 * @internal
 */
#[CoversClass(OverwriteSessionFactoryCompilerPass::class)]
class OverwriteSessionFactoryCompilerPassTest extends TestCase
{
    public function testSessionFactoryOverwrite(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('session.factory', new Definition(SymfonySessionFactory::class));

        $compilerPass = new OverwriteSessionFactoryCompilerPass();
        $compilerPass->process($container);

        static::assertTrue($container->hasDefinition('session.factory'));
        static::assertSame(SessionFactory::class, $container->getDefinition('session.factory')->getClass());
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\DependencyInjection\CompilerPass;

use League\Flysystem\FilesystemOperator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\AutoconfigureCompilerPass;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @internal
 */
#[CoversClass(AutoconfigureCompilerPass::class)]
class AutoconfigureCompilerPassTest extends TestCase
{
    public function testAutoConfigure(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $container->setDefinition('product', (new Definition(ProductDefinition::class))->setPublic(true)->setAutoconfigured(true)->setAutowired(true));

        $container->compile(true);

        static::assertTrue($container->hasDefinition('product'));
        static::assertTrue($container->getDefinition('product')->hasTag('shopware.entity.definition'));
    }

    public function testAliasing(): void
    {
        $container = new ContainerBuilder();

        $container->addCompilerPass(new AutoconfigureCompilerPass(), PassConfig::TYPE_BEFORE_OPTIMIZATION, 1000);
        $definition = new Definition(ExampleService::class);
        $definition->setPublic(true);
        $definition->setAutoconfigured(true);
        $definition->setAutowired(true);

        $container->setDefinition('shopware.filesystem.private', (new Definition(FilesystemOperator::class))->setPublic(true));
        $container->setDefinition('shopware.filesystem.public', (new Definition(FilesystemOperator::class))->setPublic(true));

        $container->setDefinition('service', $definition);

        $container->compile(true);

        static::assertTrue($container->hasDefinition('service'));

        $arg1 = $definition->getArgument(0);
        static::assertInstanceOf(Reference::class, $arg1);
        static::assertSame('shopware.filesystem.private', (string) $arg1);

        $arg2 = $definition->getArgument(1);
        static::assertInstanceOf(Reference::class, $arg2);
        static::assertSame('shopware.filesystem.public', (string) $arg2);
    }
}

/**
 * @internal
 */
class ExampleService
{
    public function __construct(
        public FilesystemOperator $privateFilesystem,
        public FilesystemOperator $publicFilesystem
    ) {
    }
}

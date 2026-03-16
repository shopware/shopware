<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationDataLoader;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentSystemDataLoaderTypeCompilerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

#[CoversClass(ContentSystemDataLoaderTypeCompilerPass::class)]
class ContentSystemDataLoaderTypeCompilerPassTest extends TestCase
{
    #[TestDox('collects loader types and sets resolver argument')]
    public function testProcessCollectsLoaderTypes(): void
    {
        $container = new ContainerBuilder();

        $resolverDefinition = new Definition(ContentSystemDataLoaderTypeResolver::class);
        $container->setDefinition(ContentSystemDataLoaderTypeResolver::class, $resolverDefinition);

        $loaderDefinition = new Definition(NavigationDataLoader::class);
        $loaderDefinition->addTag('content_system.data_loader');
        $container->setDefinition(NavigationDataLoader::class, $loaderDefinition);

        $pass = new ContentSystemDataLoaderTypeCompilerPass();
        $pass->process($container);

        $argument = $resolverDefinition->getArgument('$compiledSourceToTypes');
        static::assertIsArray($argument);
        static::assertArrayHasKey('navigation', $argument);
        static::assertSame(Tree::class, $argument['navigation'][0]['className']);
        static::assertSame([], $argument['navigation'][0]['genericParameters']);
    }

    #[TestDox('returns early when resolver definition is missing')]
    public function testProcessReturnsEarlyWithoutResolver(): void
    {
        $container = new ContainerBuilder();

        $pass = new ContentSystemDataLoaderTypeCompilerPass();
        $pass->process($container);

        static::assertFalse($container->hasDefinition(ContentSystemDataLoaderTypeResolver::class));
    }
}

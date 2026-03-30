<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationDataLoader;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\ContentSystem\Schema\ContentSystemDataLoaderTypeResolver;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentSystemDataLoaderTypeCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ContentSystemDataLoaderTypeCompilerPass::class)]
class ContentSystemDataLoaderTypeCompilerPassTest extends TestCase
{
    #[TestDox('collects loader types and sets resolver argument')]
    public function testProcessCollectsLoaderTypes(): void
    {
        [$container, $resolverDefinition] = $this->createContainerWithResolver();

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

    #[TestDox('resolves generic type with parameters')]
    public function testProcessResolvesGenericType(): void
    {
        [$container, $resolverDefinition] = $this->createContainerWithResolver();

        $loaderDefinition = new Definition(GenericStubLoader::class);
        $loaderDefinition->addTag('content_system.data_loader');
        $container->setDefinition(GenericStubLoader::class, $loaderDefinition);

        $pass = new ContentSystemDataLoaderTypeCompilerPass();
        $pass->process($container);

        $argument = $resolverDefinition->getArgument('$compiledSourceToTypes');
        static::assertIsArray($argument);
        static::assertArrayHasKey('test_generic', $argument);
        static::assertSame(EntitySearchResult::class, $argument['test_generic'][0]['className']);
        static::assertSame([ProductReviewCollection::class], $argument['test_generic'][0]['genericParameters']);
    }

    #[TestDox('skips tagged service when its class is null')]
    public function testProcessSkipsLoaderWithNullClass(): void
    {
        [$container, $resolverDefinition] = $this->createContainerWithResolver();

        $loaderDefinition = new Definition();
        $loaderDefinition->addTag('content_system.data_loader');
        $container->setDefinition('app.null_class_loader', $loaderDefinition);

        $pass = new ContentSystemDataLoaderTypeCompilerPass();
        $pass->process($container);

        $argument = $resolverDefinition->getArgument('$compiledSourceToTypes');
        static::assertIsArray($argument);
        static::assertSame([], $argument);
    }

    #[TestDox('returns early when resolver definition is missing')]
    public function testProcessReturnsEarlyWithoutResolver(): void
    {
        $container = new ContainerBuilder();

        $pass = new ContentSystemDataLoaderTypeCompilerPass();
        $pass->process($container);

        static::assertFalse($container->hasDefinition(ContentSystemDataLoaderTypeResolver::class));
    }

    #[TestDox('throws when tagged service does not extend AbstractContentDataLoader')]
    public function testProcessThrowsForNonContentDataLoaderSubclass(): void
    {
        [$container] = $this->createContainerWithResolver();

        $loaderDefinition = new Definition(\stdClass::class);
        $loaderDefinition->addTag('content_system.data_loader');
        $container->setDefinition('app.wrong_class_loader', $loaderDefinition);

        $this->expectExceptionObject(DependencyInjectionException::taggedServiceHasWrongType('app.wrong_class_loader', 'content_system.data_loader', AbstractContentDataLoader::class));

        $pass = new ContentSystemDataLoaderTypeCompilerPass();
        $pass->process($container);
    }

    #[TestDox('throws when loader has no docblock')]
    public function testProcessThrowsForMissingDocblock(): void
    {
        [$container] = $this->createContainerWithResolver();

        $loaderDefinition = new Definition(NoDocblockStubLoader::class);
        $loaderDefinition->addTag('content_system.data_loader');
        $container->setDefinition(NoDocblockStubLoader::class, $loaderDefinition);

        $this->expectExceptionObject(ContentSystemException::missingExtendsAnnotation(NoDocblockStubLoader::class));

        $pass = new ContentSystemDataLoaderTypeCompilerPass();
        $pass->process($container);
    }

    #[TestDox('throws when loader docblock has no @extends tag')]
    public function testProcessThrowsForMissingExtendsAnnotation(): void
    {
        [$container] = $this->createContainerWithResolver();

        $loaderDefinition = new Definition(MissingAnnotationStubLoader::class);
        $loaderDefinition->addTag('content_system.data_loader');
        $container->setDefinition(MissingAnnotationStubLoader::class, $loaderDefinition);

        $this->expectExceptionObject(ContentSystemException::missingExtendsAnnotation(MissingAnnotationStubLoader::class));

        $pass = new ContentSystemDataLoaderTypeCompilerPass();
        $pass->process($container);
    }

    #[TestDox('throws when @extends type parameter is not a Struct subclass')]
    public function testProcessThrowsForNonStructType(): void
    {
        [$container] = $this->createContainerWithResolver();

        $loaderDefinition = new Definition(NonStructTypeStubLoader::class);
        $loaderDefinition->addTag('content_system.data_loader');
        $container->setDefinition(NonStructTypeStubLoader::class, $loaderDefinition);

        $this->expectExceptionObject(ContentSystemException::unresolvableTypeClass(\stdClass::class, NonStructTypeStubLoader::class));

        $pass = new ContentSystemDataLoaderTypeCompilerPass();
        $pass->process($container);
    }

    /**
     * @return array{ContainerBuilder, Definition}
     */
    private function createContainerWithResolver(): array
    {
        $container = new ContainerBuilder();
        $resolverDefinition = new Definition(ContentSystemDataLoaderTypeResolver::class);
        $container->setDefinition(ContentSystemDataLoaderTypeResolver::class, $resolverDefinition);

        return [$container, $resolverDefinition];
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class GenericStubLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_generic';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

class NoDocblockStubLoader extends AbstractContentDataLoader // @phpstan-ignore missingType.generics, shopware.internalClass
{
    public static function getRequirementType(): string
    {
        return 'test_no_docblock';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @phpstan-ignore missingType.generics
 */
class MissingAnnotationStubLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_missing';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<\stdClass>
 *
 * @phpstan-ignore generics.notSubtype
 */
class NonStructTypeStubLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_non_struct';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

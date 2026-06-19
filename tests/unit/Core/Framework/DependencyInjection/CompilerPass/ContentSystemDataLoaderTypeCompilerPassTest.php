<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationDataLoader;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
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
    #[TestDox('accepts tagged loaders whose @extends annotation resolves')]
    public function testProcessAcceptsValidLoaders(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(NavigationDataLoader::class, $this->taggedLoader(NavigationDataLoader::class));
        $container->setDefinition(GenericStubLoader::class, $this->taggedLoader(GenericStubLoader::class));

        static::expectNotToPerformAssertions();

        $pass = new ContentSystemDataLoaderTypeCompilerPass();
        $pass->process($container);
    }

    #[TestDox('skips tagged service when its class is null')]
    public function testProcessSkipsLoaderWithNullClass(): void
    {
        $container = new ContainerBuilder();
        $definition = new Definition();
        $definition->addTag('content_system.data_loader');
        $container->setDefinition('app.null_class_loader', $definition);

        static::expectNotToPerformAssertions();

        $pass = new ContentSystemDataLoaderTypeCompilerPass();
        $pass->process($container);
    }

    #[TestDox('throws when tagged service does not extend AbstractContentDataLoader')]
    public function testProcessThrowsForNonContentDataLoaderSubclass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.wrong_class_loader', $this->taggedLoader(\stdClass::class));

        $this->expectExceptionObject(DependencyInjectionException::taggedServiceHasWrongType('app.wrong_class_loader', 'content_system.data_loader', AbstractContentDataLoader::class));

        (new ContentSystemDataLoaderTypeCompilerPass())->process($container);
    }

    #[TestDox('throws when loader has no docblock')]
    public function testProcessThrowsForMissingDocblock(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(NoDocblockStubLoader::class, $this->taggedLoader(NoDocblockStubLoader::class));

        $this->expectExceptionObject(ContentSystemException::missingExtendsAnnotation(NoDocblockStubLoader::class));

        (new ContentSystemDataLoaderTypeCompilerPass())->process($container);
    }

    #[TestDox('throws when loader docblock has no @extends tag')]
    public function testProcessThrowsForMissingExtendsAnnotation(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(MissingAnnotationStubLoader::class, $this->taggedLoader(MissingAnnotationStubLoader::class));

        $this->expectExceptionObject(ContentSystemException::missingExtendsAnnotation(MissingAnnotationStubLoader::class));

        (new ContentSystemDataLoaderTypeCompilerPass())->process($container);
    }

    #[TestDox('throws when @extends type parameter is not a Struct subclass')]
    public function testProcessThrowsForNonStructType(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(NonStructTypeStubLoader::class, $this->taggedLoader(NonStructTypeStubLoader::class));

        $this->expectExceptionObject(ContentSystemException::unresolvableTypeClass(\stdClass::class, NonStructTypeStubLoader::class));

        (new ContentSystemDataLoaderTypeCompilerPass())->process($container);
    }

    private function taggedLoader(string $class): Definition
    {
        $definition = new Definition($class);
        $definition->addTag('content_system.data_loader');

        return $definition;
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

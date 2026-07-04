<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationDataLoader;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentSystemDataLoaderCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ContentSystemDataLoaderCompilerPass::class)]
class ContentSystemDataLoaderCompilerPassTest extends TestCase
{
    #[TestDox('accepts tagged loaders whose @extends annotation resolves')]
    public function testProcessAcceptsValidLoaders(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(NavigationDataLoader::class, $this->taggedLoader(NavigationDataLoader::class));
        $container->setDefinition(GenericStubLoader::class, $this->taggedLoader(GenericStubLoader::class));

        static::expectNotToPerformAssertions();

        $pass = new ContentSystemDataLoaderCompilerPass();
        $pass->process($container);
    }

    #[TestDox('accepts a loader whose multi-key specification is fully valid')]
    public function testProcessAcceptsFullyValidSpecification(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ValidSpecificationLoader::class, $this->taggedLoader(ValidSpecificationLoader::class));

        static::expectNotToPerformAssertions();

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('skips tagged service when its class is null')]
    public function testProcessSkipsLoaderWithNullClass(): void
    {
        $container = new ContainerBuilder();
        $definition = new Definition();
        $definition->addTag('content_system.data_loader');
        $container->setDefinition('app.null_class_loader', $definition);

        static::expectNotToPerformAssertions();

        $pass = new ContentSystemDataLoaderCompilerPass();
        $pass->process($container);
    }

    #[TestDox('throws when tagged service does not extend AbstractContentDataLoader')]
    public function testProcessThrowsForNonContentDataLoaderSubclass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.wrong_class_loader', $this->taggedLoader(\stdClass::class));

        $this->expectExceptionObject(DependencyInjectionException::taggedServiceHasWrongType('app.wrong_class_loader', 'content_system.data_loader', AbstractContentDataLoader::class));

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when loader has no docblock')]
    public function testProcessThrowsForMissingDocblock(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(NoDocblockStubLoader::class, $this->taggedLoader(NoDocblockStubLoader::class));

        $this->expectExceptionObject(ContentSystemException::missingExtendsAnnotation(NoDocblockStubLoader::class));

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when loader docblock has no @extends tag')]
    public function testProcessThrowsForMissingExtendsAnnotation(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(MissingAnnotationStubLoader::class, $this->taggedLoader(MissingAnnotationStubLoader::class));

        $this->expectExceptionObject(ContentSystemException::missingExtendsAnnotation(MissingAnnotationStubLoader::class));

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when @extends type parameter is not a Struct subclass')]
    public function testProcessThrowsForNonStructType(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(NonStructTypeStubLoader::class, $this->taggedLoader(NonStructTypeStubLoader::class));

        $this->expectExceptionObject(ContentSystemException::unresolvableTypeClass(\stdClass::class, NonStructTypeStubLoader::class));

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when a specification declares a duplicate config key')]
    public function testProcessThrowsForDuplicateConfigKey(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(DuplicateKeyLoader::class, $this->taggedLoader(DuplicateKeyLoader::class));

        $this->expectExceptionObject(DependencyInjectionException::dataLoaderConfigKeyDuplicate(DuplicateKeyLoader::class, 'property'));

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when a propertyReference config key is not string-typed')]
    public function testProcessThrowsForNonStringPropertyReferenceKey(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(NonStringPropertyReferenceLoader::class, $this->taggedLoader(NonStringPropertyReferenceLoader::class));

        $this->expectExceptionObject(DependencyInjectionException::dataLoaderConfigKeyInvalidType(NonStringPropertyReferenceLoader::class, 'property', 'propertyReference', 'integer'));

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when a config key declares a type outside the declarable set')]
    public function testProcessThrowsForUnknownKeyType(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(UnknownKeyTypeLoader::class, $this->taggedLoader(UnknownKeyTypeLoader::class));

        $this->expectExceptionObject(DependencyInjectionException::dataLoaderConfigKeyUnknownType(
            UnknownKeyTypeLoader::class,
            'payload',
            'json',
            ConfigKeySpecification::TYPES
        ));

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when a required config key declares a default')]
    public function testProcessThrowsForRequiredKeyWithDefault(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(RequiredKeyWithDefaultLoader::class, $this->taggedLoader(RequiredKeyWithDefaultLoader::class));

        $this->expectExceptionObject(DependencyInjectionException::dataLoaderConfigKeyDefaultMismatch(
            RequiredKeyWithDefaultLoader::class,
            'property',
            'a required key must not declare a default (required means: no default and the loader cannot produce without it)'
        ));

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when a list<string> default contains a non-string element')]
    public function testProcessThrowsForMixedListDefault(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(MixedListDefaultLoader::class, $this->taggedLoader(MixedListDefaultLoader::class));

        $this->expectExceptionObject(DependencyInjectionException::dataLoaderConfigKeyDefaultMismatch(
            MixedListDefaultLoader::class,
            'associations',
            'the default value of PHP type "array" does not match the declared type "list<string>"'
        ));

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when a config key declares a default while hasDefault is false')]
    public function testProcessThrowsForDefaultWithoutHasDefault(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(DefaultWithoutHasDefaultLoader::class, $this->taggedLoader(DefaultWithoutHasDefaultLoader::class));

        $this->expectExceptionObject(DependencyInjectionException::dataLoaderConfigKeyDefaultMismatch(
            DefaultWithoutHasDefaultLoader::class,
            'limit',
            'a key without a declared default (hasDefault: false) must not carry a default value'
        ));

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when a config key\'s default type does not match the declared type')]
    public function testProcessThrowsForMismatchedDefaultType(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(MismatchedDefaultTypeLoader::class, $this->taggedLoader(MismatchedDefaultTypeLoader::class));

        $this->expectExceptionObject(DependencyInjectionException::dataLoaderConfigKeyDefaultMismatch(
            MismatchedDefaultTypeLoader::class,
            'limit',
            'the default value of PHP type "string" does not match the declared type "integer"'
        ));

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    /**
     * @param class-string $loaderClass
     */
    #[DataProvider('reservedNameProvider')]
    #[TestDox('throws when a loader uses a reserved source or config key name')]
    public function testProcessThrowsForReservedNames(string $loaderClass, DependencyInjectionException $expected): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition($loaderClass, $this->taggedLoader($loaderClass));

        $this->expectExceptionObject($expected);

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    /**
     * @return iterable<string, array{class-string, DependencyInjectionException}>
     */
    public static function reservedNameProvider(): iterable
    {
        yield 'source named loader' => [
            ReservedLoaderSourceLoader::class,
            DependencyInjectionException::dataLoaderReservedSource(ReservedLoaderSourceLoader::class, 'loader'),
        ];

        yield 'source named config' => [
            ReservedConfigSourceLoader::class,
            DependencyInjectionException::dataLoaderReservedSource(ReservedConfigSourceLoader::class, 'config'),
        ];

        yield 'config key named loader' => [
            ReservedLoaderKeyLoader::class,
            DependencyInjectionException::dataLoaderReservedConfigKey(ReservedLoaderKeyLoader::class, 'loader'),
        ];

        yield 'config key named config' => [
            ReservedConfigKeyLoader::class,
            DependencyInjectionException::dataLoaderReservedConfigKey(ReservedConfigKeyLoader::class, 'config'),
        ];
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

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class DuplicateKeyLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_duplicate_key';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::Literal, 'string', required: false),
        ]);
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class NonStringPropertyReferenceLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_non_string_property_reference';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'integer', required: true),
        ]);
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class UnknownKeyTypeLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_unknown_key_type';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('payload', ConfigKeyKind::Literal, 'json', required: false),
        ]);
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class RequiredKeyWithDefaultLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_required_key_with_default';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true, hasDefault: true, default: 'cover'),
        ]);
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class MixedListDefaultLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_mixed_list_default';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: ['media', 42]),
        ]);
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class DefaultWithoutHasDefaultLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_default_without_has_default';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('limit', ConfigKeyKind::Literal, 'integer', required: false, hasDefault: false, default: 10),
        ]);
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class MismatchedDefaultTypeLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_mismatched_default_type';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('limit', ConfigKeyKind::Literal, 'integer', required: false, hasDefault: true, default: 'three'),
        ]);
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class ReservedLoaderSourceLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'loader';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class ReservedConfigSourceLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'config';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class ReservedLoaderKeyLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_reserved_loader_key';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('loader', ConfigKeyKind::Literal, 'string', required: false),
        ]);
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class ReservedConfigKeyLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_reserved_config_key';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('config', ConfigKeyKind::Literal, 'string', required: false),
        ]);
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class ValidSpecificationLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_valid_specification';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('entity', ConfigKeyKind::EntityName, 'string', required: true),
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: true),
            new ConfigKeySpecification('rootId', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: null),
            new ConfigKeySpecification('label', ConfigKeyKind::Literal, 'string', required: false, hasDefault: true, default: 'title'),
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: ['media', 'cover']),
            new ConfigKeySpecification('depth', ConfigKeyKind::Literal, 'integer', required: false, hasDefault: true, default: 2),
            new ConfigKeySpecification('ratio', ConfigKeyKind::Literal, 'number', required: false, hasDefault: true, default: 1.5),
            new ConfigKeySpecification('enabled', ConfigKeyKind::Literal, 'boolean', required: false, hasDefault: true, default: true),
            new ConfigKeySpecification('filters', ConfigKeyKind::Literal, 'map', required: false, hasDefault: true, default: ['color' => 'red']),
        ]);
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DependencyInjection\CompilerPass;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationDataLoader;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationLoaderConfigSerializer;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfigSerializer;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeyKind;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ConfigKeySpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderConfigSpecification;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DependencyInjection\CompilerPass\ContentSystemDataLoaderCompilerPass;
use Shopware\Core\Framework\DependencyInjection\DependencyInjectionException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContentSystemDataLoaderCompilerPass::class)]
class ContentSystemDataLoaderCompilerPassTest extends TestCase
{
    #[TestDox('accepts tagged loaders whose @extends annotation resolves')]
    public function testProcessAcceptsValidLoaders(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(NavigationDataLoader::class, $this->taggedLoader(NavigationDataLoader::class));
        $container->setDefinition(GenericStubLoader::class, $this->taggedLoader(GenericStubLoader::class));
        $container->setDefinition(NavigationLoaderConfigSerializer::class, $this->taggedSerializer(NavigationLoaderConfigSerializer::class));
        $container->setDefinition(GenericStubLoaderConfigSerializer::class, $this->taggedSerializer(GenericStubLoaderConfigSerializer::class));

        $this->expectNotToPerformAssertions();

        $pass = new ContentSystemDataLoaderCompilerPass();
        $pass->process($container);
    }

    #[TestDox('accepts a loader whose multi-key specification is fully valid')]
    public function testProcessAcceptsFullyValidSpecification(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(ValidSpecificationLoader::class, $this->taggedLoader(ValidSpecificationLoader::class));
        $container->setDefinition(ValidSpecificationLoaderConfigSerializer::class, $this->taggedSerializer(ValidSpecificationLoaderConfigSerializer::class));

        $this->expectNotToPerformAssertions();

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('skips tagged service when its class is null')]
    public function testProcessSkipsLoaderWithNullClass(): void
    {
        $container = new ContainerBuilder();
        $definition = new Definition();
        $definition->addTag('content_system.data_loader');
        $container->setDefinition('app.null_class_loader', $definition);

        $this->expectNotToPerformAssertions();

        $pass = new ContentSystemDataLoaderCompilerPass();
        $pass->process($container);
    }

    /**
     * @param class-string $loaderClass
     */
    #[DataProvider('throwsForInvalidLoaderClassProvider')]
    #[TestDox('throws when a tagged loader class fails structural validation: $_dataName')]
    public function testProcessThrowsForInvalidLoaderClass(string $serviceId, string $loaderClass, \Exception $expected): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition($serviceId, $this->taggedLoader($loaderClass));

        $this->expectExceptionObject($expected);

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    /**
     * @param class-string $loaderClass
     */
    #[DataProvider('throwsForInvalidConfigSpecificationProvider')]
    #[TestDox('throws when a loader\'s config specification is invalid: $_dataName')]
    public function testProcessThrowsForInvalidConfigSpecification(string $loaderClass, DependencyInjectionException $expected): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition($loaderClass, $this->taggedLoader($loaderClass));

        $this->expectExceptionObject($expected);

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    /**
     * @param class-string $loaderClass
     */
    #[DataProvider('throwsForReservedNamesProvider')]
    #[TestDox('throws when a loader uses a reserved source or config key name')]
    public function testProcessThrowsForReservedNames(string $loaderClass, DependencyInjectionException $expected): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition($loaderClass, $this->taggedLoader($loaderClass));

        $this->expectExceptionObject($expected);

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when no tagged config serializer declares a loader\'s source')]
    public function testProcessThrowsForLoaderSourceWithoutConfigSerializer(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(GenericStubLoader::class, $this->taggedLoader(GenericStubLoader::class));

        $this->expectExceptionObject(
            DependencyInjectionException::dataLoaderSourceWithoutConfigSerializer(GenericStubLoader::class, 'test_generic')
        );

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('accepts a config serializer whose source no loader declares')]
    public function testProcessAcceptsConfigSerializerWithoutMatchingLoader(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(GenericStubLoader::class, $this->taggedLoader(GenericStubLoader::class));
        $container->setDefinition(GenericStubLoaderConfigSerializer::class, $this->taggedSerializer(GenericStubLoaderConfigSerializer::class));
        $container->setDefinition(OrphanSourceConfigSerializer::class, $this->taggedSerializer(OrphanSourceConfigSerializer::class));

        $this->expectNotToPerformAssertions();

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('skips tagged config serializers that cannot be introspected')]
    public function testProcessSkipsUnintrospectableConfigSerializers(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(GenericStubLoader::class, $this->taggedLoader(GenericStubLoader::class));
        $container->setDefinition(GenericStubLoaderConfigSerializer::class, $this->taggedSerializer(GenericStubLoaderConfigSerializer::class));

        $abstract = $this->taggedSerializer(StubConfigSerializer::class);
        $abstract->setAbstract(true);
        $container->setDefinition('app.abstract_config_serializer', $abstract);

        // A class that exists and is concrete, so it clears the earlier class-exists and non-abstract guards,
        // but does not implement AbstractContentDataLoaderConfigSerializer — the one guard the other two
        // fixtures in this test never exercise.
        $container->setDefinition('app.non_serializer_config_serializer', $this->taggedSerializer(\stdClass::class));

        $this->expectNotToPerformAssertions();

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('skips a tagged loader whose definition is abstract')]
    public function testProcessSkipsAbstractLoaderDefinition(): void
    {
        $container = new ContainerBuilder();

        // No config serializer is registered for this loader's source, so a definition that is not skipped reaches
        // the coverage check and throws there.
        $abstract = $this->taggedLoader(GenericStubLoader::class);
        $abstract->setAbstract(true);
        $container->setDefinition('app.abstract_loader_definition', $abstract);

        $this->expectNotToPerformAssertions();

        (new ContentSystemDataLoaderCompilerPass())->process($container);
    }

    #[TestDox('throws when a concrete definition registers a PHP-abstract loader class')]
    public function testProcessThrowsForAbstractLoaderClass(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('app.abstract_class_loader', $this->taggedLoader(AbstractStubLoader::class));

        try {
            (new ContentSystemDataLoaderCompilerPass())->process($container);

            static::fail('Expected the compiler pass to reject the abstract loader class.');
        } catch (DependencyInjectionException $exception) {
            static::assertStringContainsString('app.abstract_class_loader', $exception->getMessage());
            static::assertStringContainsString(AbstractStubLoader::class, $exception->getMessage());
            static::assertSame(DependencyInjectionException::DATA_LOADER_CLASS_IS_ABSTRACT, $exception->getErrorCode());
        }
    }

    #[TestDox('throws when two tagged loaders declare the same source')]
    public function testProcessThrowsForDuplicateLoaderSource(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(FirstDuplicateSourceLoader::class, $this->taggedLoader(FirstDuplicateSourceLoader::class));
        $container->setDefinition(SecondDuplicateSourceLoader::class, $this->taggedLoader(SecondDuplicateSourceLoader::class));
        $container->setDefinition(DuplicateSourceLoaderConfigSerializer::class, $this->taggedSerializer(DuplicateSourceLoaderConfigSerializer::class));

        try {
            (new ContentSystemDataLoaderCompilerPass())->process($container);

            static::fail('Expected the compiler pass to reject the second loader declaring an already-declared source.');
        } catch (DependencyInjectionException $exception) {
            static::assertSame(DependencyInjectionException::DATA_LOADER_DUPLICATE_SOURCE, $exception->getErrorCode());
            static::assertStringContainsString(FirstDuplicateSourceLoader::class, $exception->getMessage());
            static::assertStringContainsString(SecondDuplicateSourceLoader::class, $exception->getMessage());
            static::assertStringContainsString('test_duplicate_source', $exception->getMessage());
        }
    }

    /**
     * @return iterable<string, array{string, class-string, \Exception}>
     */
    public static function throwsForInvalidLoaderClassProvider(): iterable
    {
        yield 'non-subclass of AbstractContentDataLoader' => [
            'app.wrong_class_loader',
            \stdClass::class,
            DependencyInjectionException::taggedServiceHasWrongType('app.wrong_class_loader', 'content_system.data_loader', AbstractContentDataLoader::class),
        ];

        yield 'missing docblock' => [
            NoDocblockStubLoader::class,
            NoDocblockStubLoader::class,
            ContentSystemException::missingExtendsAnnotation(NoDocblockStubLoader::class),
        ];

        yield 'docblock has no @extends tag' => [
            MissingAnnotationStubLoader::class,
            MissingAnnotationStubLoader::class,
            ContentSystemException::missingExtendsAnnotation(MissingAnnotationStubLoader::class),
        ];

        yield '@extends type parameter is not a Struct subclass' => [
            NonStructTypeStubLoader::class,
            NonStructTypeStubLoader::class,
            ContentSystemException::unresolvableTypeClass(\stdClass::class, NonStructTypeStubLoader::class),
        ];
    }

    /**
     * @return iterable<string, array{class-string, DependencyInjectionException}>
     */
    public static function throwsForInvalidConfigSpecificationProvider(): iterable
    {
        yield 'duplicate config key' => [
            DuplicateKeyLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyDuplicate(DuplicateKeyLoader::class, 'property'),
        ];

        yield 'propertyReference config key is not string-typed' => [
            NonStringPropertyReferenceLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyInvalidType(NonStringPropertyReferenceLoader::class, 'property', 'propertyReference', 'integer'),
        ];

        yield 'config key declares a type outside the declarable set' => [
            UnknownKeyTypeLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyUnknownType(
                UnknownKeyTypeLoader::class,
                'payload',
                'json',
                ConfigKeySpecification::TYPES
            ),
        ];

        yield 'required config key declares a default' => [
            RequiredKeyWithDefaultLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyDefaultMismatch(
                RequiredKeyWithDefaultLoader::class,
                'property',
                'a required key must not declare a default (required means: no default and the loader cannot produce without it)'
            ),
        ];

        yield 'list<string> default contains a non-string element' => [
            MixedListDefaultLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyDefaultMismatch(
                MixedListDefaultLoader::class,
                'associations',
                'the default value of PHP type "array" does not match the declared type "list<string>"'
            ),
        ];

        yield 'config key declares a default while hasDefault is false' => [
            DefaultWithoutHasDefaultLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyDefaultMismatch(
                DefaultWithoutHasDefaultLoader::class,
                'limit',
                'a key without a declared default (hasDefault: false) must not carry a default value'
            ),
        ];

        yield 'config key\'s default type does not match the declared type' => [
            MismatchedDefaultTypeLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyDefaultMismatch(
                MismatchedDefaultTypeLoader::class,
                'limit',
                'the default value of PHP type "string" does not match the declared type "integer"'
            ),
        ];

        yield 'config key declares a referenced type outside the declarable set' => [
            UnknownReferencedTypeLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyUnknownReferencedType(
                UnknownReferencedTypeLoader::class,
                'property',
                'list<integer>',
                ConfigKeySpecification::REFERENCED_TYPES
            ),
        ];

        yield 'non-reference config key declares a referenced type' => [
            ReferencedTypeOnLiteralLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyReferencedTypeMisplaced(
                ReferencedTypeOnLiteralLoader::class,
                'associations',
                'literal'
            ),
        ];

        yield 'merging config key is not a reference' => [
            MergeFromLiteralLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyInvalidMerge(
                MergeFromLiteralLoader::class,
                'associationOverride',
                'only a propertyReference key can merge into another key, this one has kind "literal"'
            ),
        ];

        yield 'merging config key does not reference a list' => [
            MergeFromStringReferenceLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyInvalidMerge(
                MergeFromStringReferenceLoader::class,
                'associationOverride',
                'a merging key must reference a "list<string>" value, this one references "string"'
            ),
        ];

        yield 'config key merges into itself' => [
            SelfMergeLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyInvalidMerge(
                SelfMergeLoader::class,
                'associations',
                'a key cannot merge into itself'
            ),
        ];

        yield 'merge target is not declared in the same specification' => [
            UnknownMergeTargetLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyInvalidMerge(
                UnknownMergeTargetLoader::class,
                'associationOverride',
                'the merge target "associations" is not declared in the same specification'
            ),
        ];

        yield 'merge target is not a list-typed literal key' => [
            NonLiteralMergeTargetLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyInvalidMerge(
                NonLiteralMergeTargetLoader::class,
                'associationOverride',
                'the merge target "associations" must be a literal key of type "list<string>", got kind "propertyReference" of type "string"'
            ),
        ];

        yield 'two merger keys target the same key' => [
            DuplicateMergeTargetLoader::class,
            DependencyInjectionException::dataLoaderConfigKeyInvalidMerge(
                DuplicateMergeTargetLoader::class,
                'secondOverride',
                'the merge target "associations" is already claimed by key "firstOverride"; at most one merger key may target a given key'
            ),
        ];
    }

    /**
     * @return iterable<string, array{class-string, DependencyInjectionException}>
     */
    public static function throwsForReservedNamesProvider(): iterable
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

    private function taggedSerializer(string $class): Definition
    {
        $definition = new Definition($class);
        $definition->addTag('content_system.config_serializer');

        return $definition;
    }
}

/**
 * @internal
 */
readonly class StubLoaderConfig extends AbstractContentDataLoaderConfig
{
    public function jsonSerialize(): array
    {
        return [];
    }
}

/**
 * Abstract on purpose: it doubles as the fixture for a tagged abstract definition, where getSource() is still
 * abstract and a static call on it would raise a PHP Error rather than fail the build readably.
 *
 * @internal
 */
abstract class StubConfigSerializer extends AbstractContentDataLoaderConfigSerializer
{
    public function decode(array $data): AbstractContentDataLoaderConfig
    {
        return new StubLoaderConfig();
    }

    public function encode(AbstractContentDataLoaderConfig $config): array
    {
        return $config->jsonSerialize();
    }
}

/**
 * @internal
 */
class GenericStubLoaderConfigSerializer extends StubConfigSerializer
{
    public static function getSource(): string
    {
        return 'test_generic';
    }
}

/**
 * @internal
 */
class ValidSpecificationLoaderConfigSerializer extends StubConfigSerializer
{
    public static function getSource(): string
    {
        return 'test_valid_specification';
    }
}

/**
 * @internal
 */
class OrphanSourceConfigSerializer extends StubConfigSerializer
{
    public static function getSource(): string
    {
        return 'test_orphan_source';
    }
}

/**
 * @internal
 */
class DuplicateSourceLoaderConfigSerializer extends StubConfigSerializer
{
    public static function getSource(): string
    {
        return 'test_duplicate_source';
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * Abstract on purpose, with getRequirementType() left abstract: the fixture for a PHP-abstract class registered
 * under a concrete definition, where a static call on it would raise a PHP Error rather than fail the build readably.
 *
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
abstract class AbstractStubLoader extends AbstractContentDataLoader
{
}

class NoDocblockStubLoader extends AbstractContentDataLoader // @phpstan-ignore missingType.generics, shopware.internalClass
{
    public static function getRequirementType(): string
    {
        return 'test_no_docblock';
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
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
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::PropertyReference, 'string', required: false, referencedType: 'list<string>', mergesInto: 'associations'),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class UnknownReferencedTypeLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_unknown_referenced_type';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('property', ConfigKeyKind::PropertyReference, 'string', required: false, referencedType: 'list<integer>'),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class ReferencedTypeOnLiteralLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_referenced_type_on_literal';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, referencedType: 'list<string>'),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class MergeFromLiteralLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_merge_from_literal';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::Literal, 'list<string>', required: false, mergesInto: 'associations'),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class MergeFromStringReferenceLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_merge_from_string_reference';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::PropertyReference, 'string', required: false, mergesInto: 'associations'),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class SelfMergeLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_self_merge';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::PropertyReference, 'string', required: false, referencedType: 'list<string>', mergesInto: 'associations'),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class UnknownMergeTargetLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_unknown_merge_target';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::PropertyReference, 'string', required: false, referencedType: 'list<string>', mergesInto: 'associations'),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class NonLiteralMergeTargetLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_non_literal_merge_target';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::PropertyReference, 'string', required: false),
            new ConfigKeySpecification('associationOverride', ConfigKeyKind::PropertyReference, 'string', required: false, referencedType: 'list<string>', mergesInto: 'associations'),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * The first of a pair declaring one source: registered first, so it is the loader already in the map when the
 * second one reaches the duplicate guard.
 *
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class FirstDuplicateSourceLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_duplicate_source';
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class SecondDuplicateSourceLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_duplicate_source';
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class DuplicateMergeTargetLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_duplicate_merge_target';
    }

    public function configSpecification(): LoaderConfigSpecification
    {
        return new LoaderConfigSpecification([
            new ConfigKeySpecification('associations', ConfigKeyKind::Literal, 'list<string>', required: false, hasDefault: true, default: []),
            new ConfigKeySpecification('firstOverride', ConfigKeyKind::PropertyReference, 'string', required: false, referencedType: 'list<string>', mergesInto: 'associations'),
            new ConfigKeySpecification('secondOverride', ConfigKeyKind::PropertyReference, 'string', required: false, referencedType: 'list<string>', mergesInto: 'associations'),
        ]);
    }

    public function load(LoaderInputs $inputs, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

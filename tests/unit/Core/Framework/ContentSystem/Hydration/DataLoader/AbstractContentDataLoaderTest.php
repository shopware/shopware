<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentSystemDataLoaderTypeDescriptor;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Struct\Struct;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AbstractContentDataLoader::class)]
class AbstractContentDataLoaderTest extends TestCase
{
    /**
     * @param class-string<AbstractContentDataLoader<Struct>> $loaderClass
     * @param list<class-string> $expectedGenericParams
     */
    #[DataProvider('resolvesProvidedDataProvider')]
    #[TestDox('resolves return type for $loaderClass')]
    public function testResolvesProvidedData(string $loaderClass, string $expectedClassName, array $expectedGenericParams = []): void
    {
        $descriptor = $loaderClass::getProvidedData();

        static::assertSame($expectedClassName, $descriptor->className);
        static::assertSame($expectedGenericParams, $descriptor->genericParameters);
    }

    #[TestDox('leaves types unchanged by default')]
    public function testOverrideProvidedTypesLeavesTypesUnchanged(): void
    {
        $loader = new SimpleStubLoader();
        $input = [new ContentSystemDataLoaderTypeDescriptor(Tree::class)];

        $types = $loader->overrideProvidedTypes($input);

        static::assertCount(1, $types);
        static::assertSame(Tree::class, $types[0]->className);
    }

    #[DataProvider('throwsForInvalidLoaderDeclarationProvider')]
    #[TestDox('throws for invalid loader declaration: $_dataName')]
    public function testThrowsForInvalidLoaderDeclaration(string $loaderClass, \Exception $expectedException): void
    {
        $this->expectExceptionObject($expectedException);

        $loaderClass::getProvidedData();
    }

    /**
     * @return \Generator<string, array{class-string, class-string, list<class-string>}>
     */
    public static function resolvesProvidedDataProvider(): \Generator
    {
        yield 'identifier type resolves to Tree' => [SimpleStubLoader::class, Tree::class, []];
        yield 'generic type resolves outer class and captures param' => [GenericStubLoader::class, EntitySearchResult::class, [ProductReviewCollection::class]];
    }

    /**
     * @return \Generator<string, array{class-string, \Exception}>
     */
    public static function throwsForInvalidLoaderDeclarationProvider(): \Generator
    {
        yield 'missing docblock entirely' => [NoDocblockStubLoader::class, ContentSystemException::missingExtendsAnnotation(NoDocblockStubLoader::class)];
        yield 'docblock without @extends tag' => [MissingAnnotationStubLoader::class, ContentSystemException::missingExtendsAnnotation(MissingAnnotationStubLoader::class)];
        yield 'identifier type not a Struct subclass' => [NonStructTypeStubLoader::class, ContentSystemException::unresolvableTypeClass(\stdClass::class, NonStructTypeStubLoader::class)];
        yield 'generic outer type not a Struct subclass' => [GenericNonStructOuterStubLoader::class, ContentSystemException::unresolvableTypeClass(\ArrayObject::class, GenericNonStructOuterStubLoader::class)];
        yield 'generic parameter not a Struct subclass' => [GenericNonStructParamStubLoader::class, ContentSystemException::unresolvableTypeClass(\stdClass::class, GenericNonStructParamStubLoader::class)];
        yield 'type node neither identifier nor generic' => [UnsupportedTypeNodeStubLoader::class, ContentSystemException::unsupportedTypeNode(UnionTypeNode::class)];
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<Tree>
 */
class SimpleStubLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_simple';
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
 * @extends AbstractContentDataLoader<Tree|null>
 *
 * @phpstan-ignore generics.notSubtype
 */
class UnsupportedTypeNodeStubLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_unsupported_type';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<\ArrayObject<int, \stdClass>>
 *
 * @phpstan-ignore generics.notSubtype
 */
class GenericNonStructOuterStubLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_generic_non_struct_outer';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<\stdClass>>
 *
 * @phpstan-ignore generics.notSubtype
 */
class GenericNonStructParamStubLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_generic_non_struct_param';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound();
    }
}

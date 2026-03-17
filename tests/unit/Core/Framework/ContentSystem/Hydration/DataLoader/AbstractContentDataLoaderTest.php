<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Hydration\DataLoader;

use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Payment\ContentSystem\DataLoader\PaymentMethodDataLoader;
use Shopware\Core\Checkout\Payment\PaymentMethodCollection;
use Shopware\Core\Checkout\Shipping\ContentSystem\DataLoader\ShippingMethodDataLoader;
use Shopware\Core\Checkout\Shipping\ShippingMethodCollection;
use Shopware\Core\Content\Breadcrumb\ContentSystem\DataLoader\BreadcrumbDataLoader;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Content\Category\CategoryCollection;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\NavigationDataLoader;
use Shopware\Core\Content\Category\ContentSystem\DataLoader\ServiceMenuDataLoader;
use Shopware\Core\Content\Category\Tree\Tree;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSearchDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSuggestDataLoader;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\ContentDataLoaderResult;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\System\Currency\ContentSystem\DataLoader\CurrencyDataLoader;
use Shopware\Core\System\Currency\CurrencyCollection;
use Shopware\Core\System\Language\ContentSystem\DataLoader\LanguageDataLoader;
use Shopware\Core\System\Language\LanguageCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AbstractContentDataLoader::class)]
class AbstractContentDataLoaderTest extends TestCase
{
    #[TestDox('returns empty override list by default')]
    public function testOverrideProvidedTypesReturnsEmptyByDefault(): void
    {
        $loader = new SimpleStubLoader();

        static::assertSame([], $loader->overrideProvidedTypes());
    }

    #[TestDox('resolves return type from simple class declaration')]
    public function testSimpleExtendsAnnotation(): void
    {
        $descriptor = SimpleStubLoader::getProvidedData();

        static::assertSame(Tree::class, $descriptor->className);
        static::assertSame([], $descriptor->genericParameters);
    }

    #[TestDox('resolves return type from generic class declaration')]
    public function testNestedGenericExtendsAnnotation(): void
    {
        $descriptor = GenericStubLoader::getProvidedData();

        static::assertSame(EntitySearchResult::class, $descriptor->className);
        static::assertSame([ProductReviewCollection::class], $descriptor->genericParameters);
    }

    /**
     * @param class-string $loaderClass
     * @param list<class-string> $expectedGenericParams
     */
    #[DataProvider('domainLoaderAnnotationProvider')]
    #[TestDox('resolves return type for $loaderClass')]
    public function testDomainLoaderAnnotation(string $loaderClass, string $expectedClassName, array $expectedGenericParams = []): void
    {
        static::assertTrue(
            is_subclass_of($loaderClass, AbstractContentDataLoader::class),
            $loaderClass . ' is not a subclass of AbstractContentDataLoader'
        );
        $descriptor = $loaderClass::getProvidedData();
        static::assertSame($expectedClassName, $descriptor->className);
        static::assertSame($expectedGenericParams, $descriptor->genericParameters);
    }

    #[DataProvider('invalidAnnotationProvider')]
    #[TestDox('throws for invalid annotation: $description')]
    public function testThrowsForInvalidAnnotation(string $loaderClass, \Exception $expectedException, string $description): void
    {
        $this->expectExceptionObject($expectedException);

        $loaderClass::getProvidedData();
    }

    /**
     * @return \Generator<string, array{class-string, class-string, list<class-string>}>
     */
    public static function domainLoaderAnnotationProvider(): \Generator
    {
        yield NavigationDataLoader::class => [NavigationDataLoader::class, Tree::class, []];
        yield ServiceMenuDataLoader::class => [ServiceMenuDataLoader::class, CategoryCollection::class, []];
        yield ProductListingDataLoader::class => [ProductListingDataLoader::class, ProductListingResult::class, []];
        yield ProductReviewDataLoader::class => [ProductReviewDataLoader::class, EntitySearchResult::class, [ProductReviewCollection::class]];
        yield ProductSearchDataLoader::class => [ProductSearchDataLoader::class, ProductListingResult::class, []];
        yield ProductSuggestDataLoader::class => [ProductSuggestDataLoader::class, ProductListingResult::class, []];
        yield CrossSellingDataLoader::class => [CrossSellingDataLoader::class, CrossSellingElementCollection::class, []];
        yield BreadcrumbDataLoader::class => [BreadcrumbDataLoader::class, BreadcrumbCollection::class, []];
        yield PaymentMethodDataLoader::class => [PaymentMethodDataLoader::class, PaymentMethodCollection::class, []];
        yield ShippingMethodDataLoader::class => [ShippingMethodDataLoader::class, ShippingMethodCollection::class, []];
        yield LanguageDataLoader::class => [LanguageDataLoader::class, LanguageCollection::class, []];
        yield CurrencyDataLoader::class => [CurrencyDataLoader::class, CurrencyCollection::class, []];
    }

    /**
     * @return \Generator<string, array{class-string, \Exception, string}>
     */
    public static function invalidAnnotationProvider(): \Generator
    {
        yield 'missing docblock entirely' => [NoDocblockStubLoader::class, ContentSystemException::missingExtendsAnnotation(NoDocblockStubLoader::class), 'missing docblock entirely'];
        yield 'docblock without @extends tag' => [MissingAnnotationStubLoader::class, ContentSystemException::missingExtendsAnnotation(MissingAnnotationStubLoader::class), 'docblock without @extends tag'];
        yield 'identifier type not a Struct subclass' => [NonStructTypeStubLoader::class, ContentSystemException::unresolvableTypeClass(\stdClass::class, NonStructTypeStubLoader::class), 'identifier type not a Struct subclass'];
        yield 'generic outer type not a Struct subclass' => [GenericNonStructOuterStubLoader::class, ContentSystemException::unresolvableTypeClass(\ArrayObject::class, GenericNonStructOuterStubLoader::class), 'generic outer type not a Struct subclass'];
        yield 'generic parameter not a Struct subclass' => [GenericNonStructParamStubLoader::class, ContentSystemException::unresolvableTypeClass(\stdClass::class, GenericNonStructParamStubLoader::class), 'generic parameter not a Struct subclass'];
        yield 'type node neither identifier nor generic' => [UnsupportedTypeNodeStubLoader::class, ContentSystemException::unsupportedTypeNode(UnionTypeNode::class), 'type node neither identifier nor generic'];
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

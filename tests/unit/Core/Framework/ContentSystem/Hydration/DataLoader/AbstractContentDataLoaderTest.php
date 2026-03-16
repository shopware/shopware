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
    #[TestDox('parses simple @extends annotation')]
    public function testSimpleExtendsAnnotation(): void
    {
        $descriptor = SimpleTestLoader::getProvidedData();

        static::assertSame(Tree::class, $descriptor->className);
        static::assertSame([], $descriptor->genericParameters);
    }

    #[TestDox('parses nested generic @extends annotation')]
    public function testNestedGenericExtendsAnnotation(): void
    {
        $descriptor = GenericTestLoader::getProvidedData();

        static::assertSame(EntitySearchResult::class, $descriptor->className);
        static::assertSame([ProductReviewCollection::class], $descriptor->genericParameters);
    }

    #[TestDox('throws when @extends annotation is missing')]
    public function testThrowsWhenExtendsMissing(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::missingExtendsAnnotation(MissingAnnotationTestLoader::class)
        );

        MissingAnnotationTestLoader::getProvidedData();
    }

    #[TestDox('throws when resolved type class is not a Struct subclass')]
    public function testThrowsWhenTypeClassIsNotStruct(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::unresolvableTypeClass(\stdClass::class, NonStructTypeTestLoader::class)
        );

        NonStructTypeTestLoader::getProvidedData();
    }

    #[TestDox('throws when type node is neither generic nor identifier')]
    public function testThrowsWhenTypeNodeIsUnsupported(): void
    {
        $this->expectExceptionObject(
            ContentSystemException::unsupportedTypeNode(UnionTypeNode::class)
        );

        UnsupportedTypeNodeTestLoader::getProvidedData();
    }

    /**
     * @param class-string $loaderClass
     * @param list<class-string> $expectedGenericParams
     */
    #[DataProvider('parsesExtendsAnnotationProvider')]
    #[TestDox('parses @extends for $loaderClass')]
    public function testDomainLoaderAnnotation(string $loaderClass, string $expectedClassName, array $expectedGenericParams = []): void
    {
        static::assertTrue(is_subclass_of($loaderClass, AbstractContentDataLoader::class));
        $descriptor = $loaderClass::getProvidedData();
        static::assertSame($expectedClassName, $descriptor->className);
        static::assertSame($expectedGenericParams, $descriptor->genericParameters);
    }

    /**
     * @return \Generator<string, array{class-string, class-string, list<class-string>}>
     */
    public static function parsesExtendsAnnotationProvider(): \Generator
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
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<Tree>
 */
class SimpleTestLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_simple';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound(); // @phpstan-ignore return.type
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<EntitySearchResult<ProductReviewCollection>>
 */
class GenericTestLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_generic';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound(); // @phpstan-ignore return.type
    }
}

/**
 * @internal
 *
 * @phpstan-ignore missingType.generics
 */
class MissingAnnotationTestLoader extends AbstractContentDataLoader
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
class NonStructTypeTestLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_non_struct';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound(); // @phpstan-ignore return.type
    }
}

/**
 * @internal
 *
 * @extends AbstractContentDataLoader<Tree|null>
 *
 * @phpstan-ignore generics.notSubtype
 */
class UnsupportedTypeNodeTestLoader extends AbstractContentDataLoader
{
    public static function getRequirementType(): string
    {
        return 'test_unsupported_type';
    }

    public function load(ContentElement $element, DataRequirement $requirement, SalesChannelContext $context, Request $request): ContentDataLoaderResult
    {
        return ContentDataLoaderResult::notFound(); // @phpstan-ignore return.type
    }
}

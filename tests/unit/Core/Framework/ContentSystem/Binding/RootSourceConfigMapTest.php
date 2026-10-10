<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\RootSourceConfigMap;
use Shopware\Core\Framework\ContentSystem\ContentSystemException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RootSourceConfigMap::class)]
class RootSourceConfigMapTest extends TestCase
{
    private const SPECIFICATION_ID = 'core:Sw:Navigation:Breadcrumb';

    private const KEY = 'breadcrumb';

    #[TestDox('scopeMap returns the inner map, and isScoped is true, for a scoped value')]
    public function testScopedValue(): void
    {
        $inner = ['product' => 'productId', 'category' => 'categoryId'];
        $value = [RootSourceConfigMap::MARKER => $inner];

        static::assertSame($inner, RootSourceConfigMap::scopeMap($value));
        static::assertTrue(RootSourceConfigMap::isScoped($value));
    }

    #[DataProvider('nonScopedValueProvider')]
    #[TestDox('scopeMap returns null, and isScoped is false, for $_dataName')]
    public function testNonScopedValue(mixed $value): void
    {
        static::assertNull(RootSourceConfigMap::scopeMap($value));
        static::assertFalse(RootSourceConfigMap::isScoped($value));
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function nonScopedValueProvider(): iterable
    {
        yield 'a scalar string' => ['productId'];
        yield 'null' => [null];
        yield 'an integer' => [42];
        yield 'a list' => [['manufacturer', 'cover']];
        yield 'a plain associative array' => [['product' => 'x']];
        yield 'an empty array' => [[]];
        yield 'the marker alongside another key' => [[RootSourceConfigMap::MARKER => ['product' => 'x'], 'other' => 'y']];
        yield 'the marker wrapping a non-array' => [[RootSourceConfigMap::MARKER => 'productId']];
    }

    #[TestDox('collapse leaves non-scoped config values untouched')]
    public function testCollapseLeavesNonScopedUntouched(): void
    {
        $config = ['entity' => 'media', 'associations' => ['manufacturer'], 'referrer' => '{{x}}'];

        static::assertSame($config, RootSourceConfigMap::collapse($config, 'product', self::SPECIFICATION_ID, self::KEY, 'crumb-1'));
    }

    #[TestDox('collapse picks the value for the given root source from each scoped map')]
    public function testCollapsePicksRootSourceValue(): void
    {
        $config = [
            'entity' => 'media',
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId', 'category' => 'categoryId']],
            'type' => [RootSourceConfigMap::MARKER => ['product' => 'product', 'category' => 'category']],
        ];

        static::assertSame(
            ['entity' => 'media', 'property' => 'productId', 'type' => 'product'],
            RootSourceConfigMap::collapse($config, 'product', self::SPECIFICATION_ID, self::KEY, 'crumb-1'),
        );

        static::assertSame(
            ['entity' => 'media', 'property' => 'categoryId', 'type' => 'category'],
            RootSourceConfigMap::collapse($config, 'category', self::SPECIFICATION_ID, self::KEY, 'crumb-1'),
        );
    }

    #[DataProvider('unscopedRootSourceProvider')]
    #[TestDox('collapse throws, naming the specification, the key and the element, for $_dataName')]
    public function testCollapseThrowsWithoutRootSourceEntry(?string $rootSource): void
    {
        $config = [
            'entity' => 'media',
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId']],
        ];

        try {
            RootSourceConfigMap::collapse($config, $rootSource, self::SPECIFICATION_ID, self::KEY, 'crumb-1');
            static::fail('collapse() must throw for a scoped key without an entry for the root source');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::BINDING_ROOT_SOURCE_NOT_SCOPED, $exception->getErrorCode());
            static::assertSame(Response::HTTP_BAD_REQUEST, $exception->getStatusCode());
            static::assertSame(
                ['bindingSpecificationId' => self::SPECIFICATION_ID, 'key' => self::KEY, 'elementId' => 'crumb-1', 'rootSource' => $rootSource],
                $exception->getParameters()
            );
        }
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function unscopedRootSourceProvider(): iterable
    {
        yield 'a root source the scoped map has no entry for' => ['category'];
        yield 'a null root source' => [null];
    }

    #[TestDox('branches returns the config as a single branch when it carries no scoped map')]
    public function testBranchesReturnsSingleBranchForUnscopedConfig(): void
    {
        $config = ['entity' => 'media', 'property' => 'mediaId'];

        static::assertSame([$config], RootSourceConfigMap::branches($config, self::SPECIFICATION_ID, self::KEY));
    }

    #[TestDox('branches returns a single empty branch for an empty config')]
    public function testBranchesReturnsSingleBranchForEmptyConfig(): void
    {
        static::assertSame([[]], RootSourceConfigMap::branches([], self::SPECIFICATION_ID, self::KEY));
    }

    #[TestDox('branches returns one collapsed config per root source a scoped map names')]
    public function testBranchesCollapsesOnePerRootSource(): void
    {
        $config = [
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId', 'category' => 'categoryId']],
            'type' => [RootSourceConfigMap::MARKER => ['product' => 'product', 'category' => 'category']],
            'referrerCategoryProperty' => '{{referrerCategoryId}}',
        ];

        static::assertSame([
            ['property' => 'productId', 'type' => 'product', 'referrerCategoryProperty' => '{{referrerCategoryId}}'],
            ['property' => 'categoryId', 'type' => 'category', 'referrerCategoryProperty' => '{{referrerCategoryId}}'],
        ], RootSourceConfigMap::branches($config, self::SPECIFICATION_ID, self::KEY));
    }

    #[TestDox('each branch equals the collapse of the config for the same root source')]
    public function testBranchesAgreeWithCollapseForEveryRootSource(): void
    {
        $config = [
            'entity' => 'media',
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId', 'category' => 'categoryId', 'landing_page' => 'landingPageId']],
            'type' => [RootSourceConfigMap::MARKER => ['landing_page' => 'landing_page', 'product' => 'product', 'category' => 'category']],
            'variant' => [RootSourceConfigMap::MARKER => ['category' => 'narrow', 'landing_page' => 'wide', 'product' => 'square']],
        ];

        $rootSources = ['product', 'category', 'landing_page'];

        static::assertSame(
            array_map(static fn (string $rootSource): array => RootSourceConfigMap::collapse($config, $rootSource, self::SPECIFICATION_ID, self::KEY, null), $rootSources),
            RootSourceConfigMap::branches($config, self::SPECIFICATION_ID, self::KEY),
        );
        static::assertSame(
            ['entity' => 'media', 'property' => 'landingPageId', 'type' => 'landing_page', 'variant' => 'wide'],
            RootSourceConfigMap::branches($config, self::SPECIFICATION_ID, self::KEY)[2],
        );
    }

    #[TestDox('branches throws like collapse, naming no element, for a key scoped over fewer root sources than its sibling')]
    public function testBranchesThrowsForScopedMapsNamingDifferentRootSources(): void
    {
        $config = [
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId', 'category' => 'categoryId']],
            'type' => [RootSourceConfigMap::MARKER => ['product' => 'product']],
        ];

        try {
            RootSourceConfigMap::branches($config, self::SPECIFICATION_ID, self::KEY);
            static::fail('branches() must throw for a scoped key without an entry for a root source a sibling names');
        } catch (ContentSystemException $exception) {
            static::assertSame(ContentSystemException::BINDING_ROOT_SOURCE_NOT_SCOPED, $exception->getErrorCode());
            static::assertSame(
                ['bindingSpecificationId' => self::SPECIFICATION_ID, 'key' => self::KEY, 'elementId' => null, 'rootSource' => 'category'],
                $exception->getParameters()
            );
        }
    }
}

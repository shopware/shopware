<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\ContentSystem\Binding\RootSourceConfigMap;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(RootSourceConfigMap::class)]
class RootSourceConfigMapTest extends TestCase
{
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

    #[DataProvider('anyRootSourceProvider')]
    #[TestDox('collapse returns a config without a scoped value unchanged for $_dataName')]
    public function testCollapseLeavesNonScopedUntouched(?string $rootSource): void
    {
        $config = ['entity' => 'media', 'associations' => ['manufacturer'], 'referrer' => '{{x}}'];

        static::assertSame($config, RootSourceConfigMap::collapse($config, $rootSource));
    }

    /**
     * @return iterable<string, array{string|null}>
     */
    public static function anyRootSourceProvider(): iterable
    {
        yield 'a named root source' => ['product'];
        yield 'a null root source' => [null];
    }

    /**
     * @param array<string, mixed> $expected
     */
    #[DataProvider('collapsePicksProvider')]
    #[TestDox('collapse picks the value for the given root source from each scoped map: $_dataName')]
    public function testCollapsePicksRootSourceValue(string $rootSource, array $expected): void
    {
        $config = [
            'entity' => 'media',
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId', 'category' => 'categoryId']],
            'type' => [RootSourceConfigMap::MARKER => ['product' => 'product', 'category' => 'category']],
        ];

        static::assertSame($expected, RootSourceConfigMap::collapse($config, $rootSource));
    }

    /**
     * @return iterable<string, array{string, array<string, mixed>}>
     */
    public static function collapsePicksProvider(): iterable
    {
        yield 'product' => ['product', ['entity' => 'media', 'property' => 'productId', 'type' => 'product']];
        yield 'category' => ['category', ['entity' => 'media', 'property' => 'categoryId', 'type' => 'category']];
    }

    #[DataProvider('unscopedRootSourceProvider')]
    #[TestDox('collapse returns null for $_dataName')]
    public function testCollapseReturnsNullWithoutRootSourceEntry(?string $rootSource): void
    {
        $config = [
            'entity' => 'media',
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId']],
        ];

        static::assertNull(RootSourceConfigMap::collapse($config, $rootSource));
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

        static::assertSame([$config], RootSourceConfigMap::branches($config));
    }

    #[TestDox('branches returns a single empty branch for an empty config')]
    public function testBranchesReturnsSingleBranchForEmptyConfig(): void
    {
        static::assertSame([[]], RootSourceConfigMap::branches([]));
    }

    #[TestDox('branches returns the config unchanged as its single branch when every scoped map is empty')]
    public function testBranchesReturnsSingleBranchForEmptyScopedMaps(): void
    {
        $config = ['entity' => 'media', 'property' => [RootSourceConfigMap::MARKER => []]];

        static::assertSame([$config], RootSourceConfigMap::branches($config));
    }

    #[TestDox('branches returns one collapsed config per root source, in the order the scoped maps first name them')]
    public function testBranchesCollapsesOnePerRootSource(): void
    {
        $config = [
            'entity' => 'media',
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId', 'category' => 'categoryId', 'landing_page' => 'landingPageId']],
            'type' => [RootSourceConfigMap::MARKER => ['landing_page' => 'landing_page', 'product' => 'product', 'category' => 'category']],
            'variant' => [RootSourceConfigMap::MARKER => ['category' => 'narrow', 'landing_page' => 'wide', 'product' => 'square']],
            'referrerCategoryProperty' => '{{referrerCategoryId}}',
        ];

        static::assertSame([
            ['entity' => 'media', 'property' => 'productId', 'type' => 'product', 'variant' => 'square', 'referrerCategoryProperty' => '{{referrerCategoryId}}'],
            ['entity' => 'media', 'property' => 'categoryId', 'type' => 'category', 'variant' => 'narrow', 'referrerCategoryProperty' => '{{referrerCategoryId}}'],
            ['entity' => 'media', 'property' => 'landingPageId', 'type' => 'landing_page', 'variant' => 'wide', 'referrerCategoryProperty' => '{{referrerCategoryId}}'],
        ], RootSourceConfigMap::branches($config));
    }

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('differentRootSourceSetsProvider')]
    #[TestDox('branches returns null when a key is scoped over fewer root sources than its sibling: $_dataName')]
    public function testBranchesReturnsNullForScopedMapsNamingDifferentRootSources(array $config): void
    {
        static::assertNull(RootSourceConfigMap::branches($config));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function differentRootSourceSetsProvider(): iterable
    {
        yield 'the narrower map second' => [[
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId', 'category' => 'categoryId']],
            'type' => [RootSourceConfigMap::MARKER => ['product' => 'product']],
        ]];
        yield 'the narrower map first' => [[
            'type' => [RootSourceConfigMap::MARKER => ['product' => 'product']],
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId', 'category' => 'categoryId']],
        ]];
    }
}

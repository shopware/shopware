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
    #[TestDox('returns the inner map, and reports the value as scoped')]
    public function testScopedValue(): void
    {
        $inner = ['product' => 'productId', 'category' => 'categoryId'];
        $value = [RootSourceConfigMap::MARKER => $inner];

        static::assertSame($inner, RootSourceConfigMap::scopeMap($value));
        static::assertTrue(RootSourceConfigMap::isScoped($value));
    }

    #[DataProvider('nonScopedValueProvider')]
    #[TestDox('returns null, and reports the value as not scoped, for $_dataName')]
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
    #[TestDox('returns a config without a scoped value unchanged for $_dataName')]
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
    #[TestDox('picks the value for the given root source from each scoped map: $_dataName')]
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

    #[TestDox('returns one collapsed config per root source, in the order the scoped maps first name them')]
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
    #[DataProvider('singleBranchConfigProvider')]
    #[TestDox('returns the config as its single branch for $_dataName')]
    public function testBranchesReturnsSingleBranch(array $config): void
    {
        static::assertSame([$config], RootSourceConfigMap::branches($config));
    }

    /**
     * @return iterable<string, array{array<string, mixed>}>
     */
    public static function singleBranchConfigProvider(): iterable
    {
        yield 'a config without a scoped map' => [['entity' => 'media', 'property' => 'mediaId']];
        yield 'an empty config' => [[]];
        yield 'a config whose scoped maps are all empty' => [['entity' => 'media', 'property' => [RootSourceConfigMap::MARKER => []]]];
    }

    #[DataProvider('unscopedRootSourceProvider')]
    #[TestDox('returns null for $_dataName')]
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

    /**
     * @param array<string, mixed> $config
     */
    #[DataProvider('differentRootSourceSetsProvider')]
    #[TestDox('returns null when a key is scoped over fewer root sources than its sibling: $_dataName')]
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

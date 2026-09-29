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

    #[TestDox('collapse leaves non-scoped config values untouched')]
    public function testCollapseLeavesNonScopedUntouched(): void
    {
        $config = ['entity' => 'media', 'associations' => ['manufacturer'], 'referrer' => '{{x}}'];

        static::assertSame($config, RootSourceConfigMap::collapse($config, 'product'));
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
            RootSourceConfigMap::collapse($config, 'product'),
        );

        static::assertSame(
            ['entity' => 'media', 'property' => 'categoryId', 'type' => 'category'],
            RootSourceConfigMap::collapse($config, 'category'),
        );
    }

    #[TestDox('collapse drops a scoped key whose map has no entry for the root source')]
    public function testCollapseDropsScopedKeyWithoutRootSourceEntry(): void
    {
        $config = [
            'entity' => 'media',
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId']],
        ];

        static::assertSame(['entity' => 'media'], RootSourceConfigMap::collapse($config, 'category'));
    }

    #[TestDox('collapse drops every scoped key when no root source is given')]
    public function testCollapseDropsScopedKeysWhenRootSourceNull(): void
    {
        $config = [
            'entity' => 'media',
            'property' => [RootSourceConfigMap::MARKER => ['product' => 'productId']],
        ];

        static::assertSame(['entity' => 'media'], RootSourceConfigMap::collapse($config, null));
    }
}

<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ContentSystem\Helper;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ContentSystem\Helper\ContentLayoutMetadataDeriver;

/**
 * @internal
 */
#[CoversClass(ContentLayoutMetadataDeriver::class)]
class ContentLayoutMetadataDeriverTest extends TestCase
{
    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function entityIdFieldProvider(): \Generator
    {
        yield 'product' => ['product', 'productId'];
        yield 'custom underscore type' => ['some_custom_type', 'someCustomTypeId'];
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function pathPrefixProvider(): \Generator
    {
        yield 'product' => ['product', '/product/'];
        yield 'custom underscore type' => ['some_custom_type', '/some-custom-type/'];
    }

    #[DataProvider('entityIdFieldProvider')]
    #[TestDox('derives entity ID field "$expected" from entity type "$entityType"')]
    public function testDerivesEntityIdFieldFromEntityType(string $entityType, string $expected): void
    {
        $deriver = new ContentLayoutMetadataDeriver();

        static::assertSame($expected, $deriver->deriveEntityIdField($entityType));
    }

    #[DataProvider('pathPrefixProvider')]
    #[TestDox('derives path prefix "$expected" from entity type "$entityType"')]
    public function testDerivesPathPrefixFromEntityType(string $entityType, string $expected): void
    {
        $deriver = new ContentLayoutMetadataDeriver();

        static::assertSame($expected, $deriver->derivePathPrefix($entityType));
    }

    #[TestDox('derives route pattern from entity ID field')]
    public function testDerivesRoutePatternFromEntityIdField(): void
    {
        $deriver = new ContentLayoutMetadataDeriver();

        static::assertSame('{productId}', $deriver->deriveRoutePattern('productId'));
    }
}

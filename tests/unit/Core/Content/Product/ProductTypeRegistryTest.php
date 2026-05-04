<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductTypeRegistry;

/**
 * @internal
 */
#[CoversClass(ProductTypeRegistry::class)]
class ProductTypeRegistryTest extends TestCase
{
    public function testConstructorDeduplicatesAndNormalizesTypes(): void
    {
        $registry = new ProductTypeRegistry([
            'standard',
            'standard',
            'download',
        ]);

        static::assertSame([
            'standard',
            'download',
        ], $registry->getTypes());
    }

    public function testAddTypeAppendsOnlyWhenMissing(): void
    {
        $registry = new ProductTypeRegistry(['standard']);

        $registry->addType('download');
        $registry->addType('download');

        static::assertSame([
            'standard',
            'download',
        ], $registry->getTypes());

        static::assertSame([
            'standard',
            'download',
        ], $registry->getChoices());
    }

    public function testHasTypeChecksRegisteredTypes(): void
    {
        $registry = new ProductTypeRegistry(['standard']);

        static::assertTrue($registry->hasType('standard'));
        static::assertFalse($registry->hasType('download'));
    }
}

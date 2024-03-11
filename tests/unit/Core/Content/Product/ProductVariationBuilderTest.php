<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\ProductVariationBuilder;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionCollection;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOption\PropertyGroupOptionEntity;
use Shopware\Core\Content\Property\PropertyGroupEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[CoversClass(ProductVariationBuilder::class)]
class ProductVariationBuilderTest extends TestCase
{
    /**
     * @param array<array<string, string>> $expected
     */
    #[DataProvider('buildingProvider')]
    public function testBuilding(Entity $product, array $expected): void
    {
        $builder = new ProductVariationBuilder();

        $builder->build($product);

        static::assertEquals($expected, $product->get('variation'));
    }

    public static function buildingProvider(): \Generator
    {
        yield 'Test without options' => [
            new ProductEntity(),
            [],
        ];

        yield 'Test without loaded option groups' => [
            (new ProductEntity())->assign([
                '_uniqueIdentifier' => Uuid::randomHex(),
                'options' => new PropertyGroupOptionCollection([
                    (new PropertyGroupOptionEntity())->assign([
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'name' => 'red',
                    ]),
                ]),
            ]),
            [],
        ];

        yield 'Test with valid product' => [
            (new ProductEntity())->assign([
                '_uniqueIdentifier' => Uuid::randomHex(),
                'options' => new PropertyGroupOptionCollection([
                    (new PropertyGroupOptionEntity())->assign([
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'red'],
                        'group' => (new PropertyGroupEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'color'],
                        ]),
                    ]),
                ]),
            ]),
            [
                ['group' => 'color', 'option' => 'red'],
            ],
        ];

        yield 'Test with partial entity' => [
            (new PartialEntity())->assign([
                '_uniqueIdentifier' => Uuid::randomHex(),
                'options' => new EntityCollection([
                    (new PartialEntity())->assign([
                        'id' => Uuid::randomHex(),
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'red'],
                        'group' => (new PartialEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'color'],
                        ]),
                    ]),
                ]),
            ]),
            [
                ['group' => 'color', 'option' => 'red'],
            ],
        ];

        yield 'Test with multiple options, sorted by position' => [
            (new PartialEntity())->assign([
                '_uniqueIdentifier' => Uuid::randomHex(),
                'options' => new EntityCollection([
                    (new PartialEntity())->assign([
                        'id' => Uuid::randomHex(),
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'red'],
                        'group' => (new PartialEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'color'],
                            'position' => 2,
                        ]),
                    ]),
                    (new PartialEntity())->assign([
                        'id' => Uuid::randomHex(),
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'xl'],
                        'group' => (new PartialEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'size'],
                            'position' => 1,
                        ]),
                    ]),
                ]),
            ]),
            [
                ['group' => 'size', 'option' => 'xl'],
                ['group' => 'color', 'option' => 'red'],
            ],
        ];

        yield 'Test with multiple options, sorted by group name' => [
            (new PartialEntity())->assign([
                '_uniqueIdentifier' => Uuid::randomHex(),
                'options' => new EntityCollection([
                    (new PartialEntity())->assign([
                        'id' => Uuid::randomHex(),
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'xl'],
                        'group' => (new PartialEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'size'],
                            'position' => 1,
                        ]),
                    ]),
                    (new PartialEntity())->assign([
                        'id' => Uuid::randomHex(),
                        '_uniqueIdentifier' => Uuid::randomHex(),
                        'translated' => ['name' => 'red'],
                        'group' => (new PartialEntity())->assign([
                            '_uniqueIdentifier' => Uuid::randomHex(),
                            'translated' => ['name' => 'color'],
                            'position' => 1,
                        ]),
                    ]),
                ]),
            ]),
            [
                ['group' => 'color', 'option' => 'red'],
                ['group' => 'size', 'option' => 'xl'],
            ],
        ];
    }
}

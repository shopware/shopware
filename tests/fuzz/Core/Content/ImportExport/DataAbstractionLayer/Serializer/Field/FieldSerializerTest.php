<?php declare(strict_types=1);

namespace Shopware\Tests\Fuzz\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Eris\Generator;
use Eris\Generators;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Tests\Fuzz\FuzzTestCase;

/**
 * Fuzzes FieldSerializer::deserialize() for ManyToMany association values.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
class FieldSerializerTest extends FuzzTestCase
{
    public function testMultipleSafeIdentifiersRoundTripToTheSameCountOfAssociations(): void
    {
        $serializer = new FieldSerializer();
        $field = $this->manyToManyField();
        $config = new Config([], [], []);

        $this->forAll(Generators::vector(3, $this->safeName()))
            ->then(function (array $names) use ($serializer, $field, $config): void {
                $value = implode('|', $names);

                $result = $serializer->deserialize($config, $field, $value);

                static::assertIsArray($result);
                static::assertCount(\count($names), $result);
                static::assertSame(
                    array_map(
                        static fn (string $name): array => ['id' => Uuid::fromStringToHex(mb_strtolower(trim($name)))],
                        $names
                    ),
                    array_values($result)
                );
            });
    }

    /**
     * Known bug, pinned rather than skipped: FieldSerializer::deserialize() splits on "|"
     * before validating an id, so normalizeId()'s own rejection of "|" can never fire. Once
     * fixed, this assertion will start failing - replace it with an
     * expectException(InvalidIdentifierException::class).
     */
    public function testRejectsASingleIdentifierContainingThePipeDelimiter(): void
    {
        $serializer = new FieldSerializer();
        $field = $this->manyToManyField();
        $config = new Config([], [], []);

        $this->forAll(Generators::tuple($this->safeName(), $this->safeName()))
            ->then(function (array $names) use ($serializer, $field, $config): void {
                [$left, $right] = $names;

                $result = $serializer->deserialize($config, $field, $left . '|' . $right);

                static::assertIsArray($result);
                static::assertSame(
                    [
                        ['id' => Uuid::fromStringToHex(mb_strtolower(trim($left)))],
                        ['id' => Uuid::fromStringToHex(mb_strtolower(trim($right)))],
                    ],
                    array_values($result)
                );
            });
    }

    /**
     * @phpstan-ignore missingType.generics (Eris's Generator only declares a Psalm template, not a PHPStan-compatible one)
     */
    private function safeName(): Generator
    {
        return Generators::suchThat(
            static fn (string $s): bool => !str_contains($s, '|'),
            Generators::map(static fn (string $s): string => 'n' . $s, Generators::string())
        );
    }

    private function manyToManyField(): ManyToManyAssociationField
    {
        return new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, 'product_id', 'category_id');
    }
}

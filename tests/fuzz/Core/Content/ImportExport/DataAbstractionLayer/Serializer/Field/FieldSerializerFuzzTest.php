<?php declare(strict_types=1);

namespace Shopware\Tests\Fuzz\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field;

use Eris\Generator;
use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\Exception\InvalidIdentifierException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Content\Product\Aggregate\ProductCategory\ProductCategoryDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * Fuzzes FieldSerializer::deserialize() for ManyToMany association values - the ImportExport
 * counterpart to the QueryStringParser fuzz test's finding: it round-trips values through
 * explode('|', ...) with no escaping, so a value that itself contains "|" cannot be
 * distinguished from two values separated by "|".
 *
 * FieldSerializer::normalizeId() visibly intends to guard against this - it explicitly checks
 * `str_contains($id, '|')` and throws InvalidIdentifierException - but deserialize() calls
 * explode('|', $value) BEFORE normalizeId() ever sees an individual id, so no id it receives
 * can ever still contain a "|". The guard is unreachable. testRejectsASingleIdentifierContai
 * ningThePipeDelimiter() asserts the contract that guard implies; it currently fails, which is
 * the point - it demonstrates the bug rather than working around it. See
 * .agents/skills/shopware-fuzz-tests for the pattern and .github/AGENTS.md-style "don't adjust
 * the test to make it pass" discipline this file follows.
 *
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(FieldSerializer::class)]
class FieldSerializerFuzzTest extends TestCase
{
    use TestTrait;

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
     * A single business-name-style value (e.g. a category or tag technical name a user typed,
     * not a delimiter-separated list) that happens to contain a literal "|" must be rejected -
     * per FieldSerializer::normalizeId()'s own str_contains($id, '|') guard - not silently
     * split and misinterpreted as two associations to unrelated (and likely nonexistent)
     * entities. This currently fails: see the class docblock.
     */
    public function testRejectsASingleIdentifierContainingThePipeDelimiter(): void
    {
        // :NOTE: This test currently fails...
        
        $serializer = new FieldSerializer();
        $field = $this->manyToManyField();
        $config = new Config([], [], []);

        $this->forAll($this->nameContainingPipe())
            ->then(function (string $name) use ($serializer, $field, $config): void {
                try {
                    $result = $serializer->deserialize($config, $field, $name);
                    static::fail(\sprintf(
                        'Expected InvalidIdentifierException for %s (a single value containing "|"), got: %s',
                        var_export($name, true),
                        var_export($result, true)
                    ));
                } catch (InvalidIdentifierException) {
                }
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

    /**
     * @phpstan-ignore missingType.generics (Eris's Generator only declares a Psalm template, not a PHPStan-compatible one)
     */
    private function nameContainingPipe(): Generator
    {
        return Generators::map(
            static fn (array $pair): string => $pair[0] . '|' . $pair[1],
            Generators::tuple($this->safeName(), $this->safeName())
        );
    }

    private function manyToManyField(): ManyToManyAssociationField
    {
        return new ManyToManyAssociationField('categories', CategoryDefinition::class, ProductCategoryDefinition::class, 'product_id', 'category_id');
    }
}

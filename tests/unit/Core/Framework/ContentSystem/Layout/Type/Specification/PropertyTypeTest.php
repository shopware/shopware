<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Layout\Type\Specification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Type\Specification\PropertyType;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(PropertyType::class)]
class PropertyTypeTest extends TestCase
{
    /**
     * @param string|list<string> $type
     * @param list<string> $expected
     */
    #[DataProvider('contextTypeProvider')]
    #[TestDox('reads $_dataName as accepting $expected')]
    public function testContextTypes(string|array $type, array $expected): void
    {
        static::assertSame($expected, (new PropertyType($type, false, null, null))->contextTypes());
    }

    /**
     * @return iterable<string, array{string|list<string>, list<string>}>
     */
    public static function contextTypeProvider(): iterable
    {
        yield 'a string property' => ['string', ['single']];
        yield 'an integer property' => ['integer', ['single']];
        yield 'a boolean property' => ['boolean', ['single']];
        yield 'a number property' => ['number', ['single']];

        // The distinction the Administration cannot make for itself, and the reason this method exists: the
        // gallery declares the collection and the image element the entity, and offering a candidate across
        // that line produces an element that renders nothing.
        yield 'an entity property' => [MediaEntity::class, ['single']];
        yield 'a collection property' => [MediaCollection::class, ['collection']];

        // Bare `object` names an object without naming which one, so it rules nothing out — matching how
        // MappingTypeCompatibility::permits() takes any class-typed candidate for it.
        yield 'an unconstrained object property' => ['object', ['single', 'collection']];

        yield 'a union of primitives' => [['string', 'integer'], ['single']];
        yield 'a union of an entity and a collection' => [
            [MediaEntity::class, MediaCollection::class],
            ['single', 'collection'],
        ];

        // A union repeating a kind reports it once, so the Administration's includes() check stays a plain
        // membership test rather than having to care how often a kind appears.
        yield 'a union of two collections' => [[MediaCollection::class, MediaCollection::class], ['collection']];
    }

    /**
     * The schema is what the Administration actually reads, so the derivation reaching it is the part that
     * matters; a correct contextTypes() that toSchema() drops would leave the selection UI exactly as broken.
     */
    #[TestDox('publishes the accepted context types in the schema')]
    public function testToSchemaCarriesContextTypes(): void
    {
        $schema = (new PropertyType(MediaCollection::class, false, null, null))->toSchema();

        static::assertSame(['collection'], $schema['contextTypes']);
    }
}

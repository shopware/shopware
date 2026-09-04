<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\ContentSystem\Binding;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\MediaCollection;
use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\ContentSystem\Binding\ResolvedByLoaderBranch;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityCollectionLoader\EntityCollectionLoader;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\EntityLoader\EntityLoader;
use Shopware\Core\Framework\ContentSystem\Layout\Element\StoredValue;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Struct\Struct;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ResolvedByLoaderBranch::class)]
class ResolvedByLoaderBranchTest extends TestCase
{
    #[DataProvider('classifiesReferenceFqcnProvider')]
    #[TestDox('classifies reference by FQCN: $_dataName')]
    public function testFromReferenceFqcnClassifiesByBaseClass(string $fqcn, ?ResolvedByLoaderBranch $expected): void
    {
        static::assertSame($expected, ResolvedByLoaderBranch::fromReferenceFqcn($fqcn));
    }

    #[DataProvider('returnsLoaderSourceProvider')]
    #[TestDox('returns branch-specific loader source: $_dataName')]
    public function testLoaderSourceReturnsBranchSpecificSource(ResolvedByLoaderBranch $branch, string $expected): void
    {
        static::assertSame($expected, $branch->loaderSource());
    }

    #[DataProvider('classifiesLoaderSourceProvider')]
    #[TestDox('classifies branch by loader source: $_dataName')]
    public function testFromLoaderSourceClassifiesByLoaderSource(string $source, ?ResolvedByLoaderBranch $expected): void
    {
        static::assertSame($expected, ResolvedByLoaderBranch::fromLoaderSource($source));
    }

    #[DataProvider('matchesStoredValueShapeProvider')]
    #[TestDox('validates stored value matches branch shape: $_dataName')]
    public function testMatchesStoredValueShape(ResolvedByLoaderBranch $branch, mixed $value, bool $expected): void
    {
        static::assertSame($expected, $branch->matchesStoredValueShape(StoredValue::fromDecoded($value)));
    }

    /**
     * @return iterable<string, array{string, ?ResolvedByLoaderBranch}>
     */
    public static function classifiesReferenceFqcnProvider(): iterable
    {
        yield 'an Entity subclass classifies as the Entity branch' => [MediaEntity::class, ResolvedByLoaderBranch::Entity];
        yield 'an EntityCollection subclass classifies as the EntityCollection branch' => [MediaCollection::class, ResolvedByLoaderBranch::EntityCollection];
        yield 'a class subclassing neither base class classifies as no branch' => [Struct::class, null];
    }

    /**
     * @return iterable<string, array{ResolvedByLoaderBranch, string}>
     */
    public static function returnsLoaderSourceProvider(): iterable
    {
        yield 'the Entity branch names the entity loader source' => [ResolvedByLoaderBranch::Entity, EntityLoader::SOURCE];
        yield 'the EntityCollection branch names the entity_collection loader source' => [ResolvedByLoaderBranch::EntityCollection, EntityCollectionLoader::SOURCE];
    }

    /**
     * @return iterable<string, array{string, ?ResolvedByLoaderBranch}>
     */
    public static function classifiesLoaderSourceProvider(): iterable
    {
        yield 'the entity loader source classifies as the Entity branch' => [EntityLoader::SOURCE, ResolvedByLoaderBranch::Entity];
        yield 'the entity_collection loader source classifies as the EntityCollection branch' => [EntityCollectionLoader::SOURCE, ResolvedByLoaderBranch::EntityCollection];
        yield 'an unknown loader source classifies as no branch' => ['unrelated_loader', null];
    }

    /**
     * @return iterable<string, array{ResolvedByLoaderBranch, mixed, bool}>
     */
    public static function matchesStoredValueShapeProvider(): iterable
    {
        yield 'Entity branch accepts a string' => [ResolvedByLoaderBranch::Entity, 'media-id', true];
        yield 'Entity branch rejects an integer' => [ResolvedByLoaderBranch::Entity, 42, false];
        yield 'Entity branch rejects an array' => [ResolvedByLoaderBranch::Entity, ['media-id'], false];
        yield 'EntityCollection branch accepts a list of strings' => [ResolvedByLoaderBranch::EntityCollection, ['id-1', 'id-2'], true];
        yield 'EntityCollection branch accepts an empty list' => [ResolvedByLoaderBranch::EntityCollection, [], true];
        yield 'EntityCollection branch rejects a list with a non-string entry' => [ResolvedByLoaderBranch::EntityCollection, ['id-1', 42], false];
        yield 'EntityCollection branch rejects an associative all-string array' => [ResolvedByLoaderBranch::EntityCollection, ['a' => 'id-1', 'b' => 'id-2'], false];
        yield 'EntityCollection branch rejects a string' => [ResolvedByLoaderBranch::EntityCollection, 'media-id', false];
    }
}

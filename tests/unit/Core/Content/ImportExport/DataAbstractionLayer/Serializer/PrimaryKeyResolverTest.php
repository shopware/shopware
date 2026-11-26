<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\ImportExport\DataAbstractionLayer\Serializer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\Field\FieldSerializer;
use Shopware\Core\Content\ImportExport\DataAbstractionLayer\Serializer\PrimaryKeyResolver;
use Shopware\Core\Content\ImportExport\ImportExportException;
use Shopware\Core\Content\ImportExport\Struct\Config;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityWriteGateway;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(PrimaryKeyResolver::class)]
class PrimaryKeyResolverTest extends TestCase
{
    private ParentDefinition $parentDefinition;

    protected function setUp(): void
    {
        $this->parentDefinition = new ParentDefinition();

        new StaticDefinitionInstanceRegistry(
            [$this->parentDefinition, ChildDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGateway::class),
        );
    }

    public function testResolvesExistingIdOfReverseOneToOneAssociation(): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();
        $childRepository = new StaticEntityRepository([[$childId]]);

        $record = $this->resolve($childRepository, [
            'id' => $parentId,
            'child' => ['value' => 5],
        ]);

        static::assertSame(['id' => $parentId, 'child' => ['value' => 5, 'id' => $childId]], $record);

        $criteria = $childRepository->searchedCriteria[0] ?? null;
        static::assertNotNull($criteria);
        static::assertEquals([new EqualsFilter('parentId', $parentId)], $criteria->getFilters());
    }

    public function testKeepsRecordWithoutIdWhenNoReferencedEntityExists(): void
    {
        $parentId = Uuid::randomHex();

        $record = $this->resolve(new StaticEntityRepository([[]]), [
            'id' => $parentId,
            'child' => ['value' => 5],
        ]);

        static::assertSame(['id' => $parentId, 'child' => ['value' => 5]], $record);
    }

    public function testKeepsIdGivenInTheRecord(): void
    {
        $childRepository = new StaticEntityRepository([]);
        $input = [
            'id' => Uuid::randomHex(),
            'child' => ['id' => Uuid::randomHex(), 'value' => 5],
        ];

        static::assertSame($input, $this->resolve($childRepository, $input));
        static::assertSame([], $childRepository->searchedCriteria);
    }

    public function testSkipsLookupWithoutParentId(): void
    {
        $childRepository = new StaticEntityRepository([]);
        $input = ['child' => ['value' => 5]];

        static::assertSame($input, $this->resolve($childRepository, $input));
        static::assertSame([], $childRepository->searchedCriteria);
    }

    #[DataProvider('blankAssociationIdProvider')]
    public function testResolvesIdWhenTheAssociationIdIsBlank(string $blankId): void
    {
        $parentId = Uuid::randomHex();
        $childId = Uuid::randomHex();

        $record = $this->resolve(new StaticEntityRepository([[$childId]]), [
            'id' => $parentId,
            'child' => ['id' => $blankId, 'value' => 5],
        ]);

        static::assertSame(['id' => $parentId, 'child' => ['id' => $childId, 'value' => 5]], $record);
    }

    public static function blankAssociationIdProvider(): \Generator
    {
        yield 'empty id column of an exported file' => [''];
        yield 'id column containing only whitespace' => ['  '];
    }

    #[DataProvider('parentIdNormalizationProvider')]
    public function testSearchesWithTheParentIdThatIsWritten(string $rawParentId, string $expectedParentId): void
    {
        $childRepository = new StaticEntityRepository([[Uuid::randomHex()]]);

        $record = $this->resolve($childRepository, [
            'id' => $rawParentId,
            'child' => ['value' => 5],
        ]);

        $criteria = $childRepository->searchedCriteria[0] ?? null;
        static::assertNotNull($criteria);
        static::assertEquals([new EqualsFilter('parentId', $expectedParentId)], $criteria->getFilters());
        static::assertSame($rawParentId, $record['id'], 'The record keeps the raw value for the regular deserialization');
    }

    public static function parentIdNormalizationProvider(): \Generator
    {
        yield 'uuid is trimmed and lowercased' => ['  0190A5B4C3D2E1F0A9B8C7D6E5F4A3B2 ', '0190a5b4c3d2e1f0a9b8c7d6e5f4a3b2'];
        yield 'non-uuid identifier is hashed like on write' => ['SW10000', Uuid::fromStringToHex('sw10000')];
    }

    public function testReportsAnInvalidParentIdAsRecordError(): void
    {
        $childRepository = new StaticEntityRepository([]);

        $record = $this->resolve($childRepository, [
            'id' => 'SW1|SW2',
            'child' => ['value' => 5],
        ]);

        static::assertEquals(ImportExportException::deserializationFailed('id', 'SW1|SW2', 'uuid'), $record['_error'] ?? null);
        static::assertSame([], $childRepository->searchedCriteria);
    }

    public function testSkipsLookupForRecordWithError(): void
    {
        $childRepository = new StaticEntityRepository([]);
        $input = [
            'id' => Uuid::randomHex(),
            'child' => ['value' => 5],
            '_error' => new \RuntimeException('previous error'),
        ];

        static::assertSame($input, $this->resolve($childRepository, $input));
        static::assertSame([], $childRepository->searchedCriteria);
    }

    public function testSkipsLookupForCreateOnlyImport(): void
    {
        $childRepository = new StaticEntityRepository([]);
        $input = [
            'id' => Uuid::randomHex(),
            'child' => ['value' => 5],
        ];

        $record = $this->resolve($childRepository, $input, new Config([], ['createEntities' => true, 'updateEntities' => false], []));

        static::assertSame($input, $record);
        static::assertSame([], $childRepository->searchedCriteria);
    }

    /**
     * @param StaticEntityRepository<*> $childRepository
     * @param array<string, mixed> $record
     *
     * @return array<string, mixed>
     */
    private function resolve(StaticEntityRepository $childRepository, array $record, ?Config $config = null): array
    {
        $registry = static::createStub(DefinitionInstanceRegistry::class);
        $registry->method('getRepository')->willReturnMap([[ChildDefinition::ENTITY_NAME, $childRepository]]);

        $resolver = new PrimaryKeyResolver($registry, new FieldSerializer());
        $result = $resolver->resolvePrimaryKeyFromUpdatedBy($config ?? new Config([], [], []), $this->parentDefinition, $record);

        return \is_array($result) ? $result : iterator_to_array($result);
    }
}

/**
 * @internal
 */
class ParentDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'pk_resolver_test_parent';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new OneToOneAssociationField('child', 'id', 'parent_id', ChildDefinition::class, false),
        ]);
    }
}

/**
 * @internal
 */
class ChildDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'pk_resolver_test_child';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('parent_id', 'parentId', ParentDefinition::class))->addFlags(new Required()),
            (new IntField('value', 'value'))->addFlags(new Required()),
            new OneToOneAssociationField('parent', 'parent_id', 'id', ParentDefinition::class, false),
        ]);
    }
}

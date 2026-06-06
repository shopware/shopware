<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\CustomField;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSet\CustomFieldSetCollection;
use Shopware\Core\System\CustomField\Aggregate\CustomFieldSetRelation\CustomFieldSetRelationCollection;
use Shopware\Core\System\CustomField\CustomFieldCollection;
use Shopware\Core\System\CustomField\CustomFieldSetPersister;
use Shopware\Core\System\CustomField\CustomFieldXmlLoader;
use Shopware\Core\System\CustomField\Xml\CustomFields;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;

/**
 * @internal
 */
#[CoversClass(CustomFieldSetPersister::class)]
class CustomFieldSetPersisterTest extends TestCase
{
    private Connection&MockObject $connection;

    /**
     * @var StaticEntityRepository<CustomFieldSetCollection>
     */
    private StaticEntityRepository $setRepository;

    /**
     * @var StaticEntityRepository<CustomFieldSetRelationCollection>
     */
    private StaticEntityRepository $relationRepository;

    /**
     * @var StaticEntityRepository<CustomFieldCollection>
     */
    private StaticEntityRepository $fieldRepository;

    private CustomFieldSetPersister $persister;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->setRepository = new StaticEntityRepository([]);
        $this->relationRepository = new StaticEntityRepository([]);
        $this->fieldRepository = new StaticEntityRepository([]);

        $this->persister = new CustomFieldSetPersister(
            $this->setRepository,
            $this->connection,
            $this->relationRepository,
            $this->fieldRepository,
        );
    }

    public function testSyncNewSetsForPluginUpsertsWithoutAppId(): void
    {
        // plugin behavior: existing sets are looked up by name and none exist yet
        $this->connection->method('fetchAllKeyValue')->willReturn([]);

        $this->persister->sync($this->loadFixture(), null, Context::createDefaultContext());

        $upserts = $this->setRepository->getPayloads(StaticEntityRepository::UPSERT);
        static::assertCount(2, $upserts);

        $first = $upserts[0];
        static::assertSame('test_set', $first['name']);
        static::assertArrayNotHasKey('appId', $first);
        static::assertArrayNotHasKey('id', $first);
        static::assertSame(['product', 'customer'], array_column($first['relations'], 'entityName'));
        static::assertCount(2, $first['customFields']);

        $second = $upserts[1];
        static::assertSame('test_global_set', $second['name']);
        static::assertTrue($second['global']);

        static::assertSame([], $this->setRepository->deletes);
    }

    public function testSyncNewSetsForAppUpsertsWithAppId(): void
    {
        $appId = Uuid::randomHex();

        // app behavior: existing sets are looked up by app_id and none exist yet
        $this->connection->method('fetchAllKeyValue')->willReturn([]);

        $this->persister->sync($this->loadFixture(), $appId, Context::createDefaultContext());

        $upserts = $this->setRepository->getPayloads(StaticEntityRepository::UPSERT);
        static::assertCount(2, $upserts);
        static::assertSame($appId, $upserts[0]['appId']);
        static::assertSame($appId, $upserts[1]['appId']);
    }

    public function testSyncEmptyDefinitionDeletesAllExistingSets(): void
    {
        $appId = Uuid::randomHex();
        $existingSetId = Uuid::randomHex();

        $this->connection->method('fetchAllKeyValue')->willReturn([
            Uuid::fromHexToBytes($existingSetId) => 'obsolete_set',
        ]);

        $this->persister->sync(CustomFields::fromArray([]), $appId, Context::createDefaultContext());

        static::assertSame([], $this->setRepository->getPayloads(StaticEntityRepository::UPSERT));

        $deletes = $this->setRepository->getPayloads(StaticEntityRepository::DELETE);
        static::assertSame([['id' => $existingSetId]], $deletes);
    }

    public function testSyncExistingSetCarriesIdAndDeletesObsoleteChildren(): void
    {
        $setId = Uuid::randomHex();
        $obsoleteRelationId = Uuid::randomHex();
        $obsoleteFieldId = Uuid::randomHex();

        $fixture = $this->loadFixtureSingleSet();

        $this->connection->method('fetchAllKeyValue')->willReturnCallback(
            function (string $sql) use ($setId, $obsoleteRelationId, $obsoleteFieldId): array {
                if (str_contains($sql, 'FROM custom_field_set ')) {
                    // existing set lookup by name (plugin behavior)
                    return ['test_set' => $setId];
                }

                if (str_contains($sql, 'FROM custom_field_set_relation')) {
                    // keyed by entity_name, value is the binary id; this relation is
                    // no longer defined in the XML -> obsolete
                    return ['obsolete_entity' => Uuid::fromHexToBytes($obsoleteRelationId)];
                }

                // keyed by field name, value is the binary id; this field is
                // no longer defined in the XML -> obsolete
                return ['obsolete_field' => Uuid::fromHexToBytes($obsoleteFieldId)];
            }
        );

        $this->persister->sync($fixture, null, Context::createDefaultContext());

        $upserts = $this->setRepository->getPayloads(StaticEntityRepository::UPSERT);
        static::assertCount(1, $upserts);
        // name is immutable, only the id is carried over for an update
        static::assertSame($setId, $upserts[0]['id']);
        static::assertArrayNotHasKey('name', $upserts[0]);

        static::assertSame(
            [['id' => $obsoleteRelationId]],
            $this->relationRepository->getPayloads(StaticEntityRepository::DELETE)
        );
        static::assertSame(
            [['id' => $obsoleteFieldId]],
            $this->fieldRepository->getPayloads(StaticEntityRepository::DELETE)
        );
    }

    public function testSyncForAppDeletesDuplicateNamedSets(): void
    {
        $appId = Uuid::randomHex();
        $duplicateA = Uuid::randomHex();
        $duplicateB = Uuid::randomHex();

        // two sets share the same name -> both must be deleted and recreated
        $this->connection->method('fetchAllKeyValue')->willReturn([
            Uuid::fromHexToBytes($duplicateA) => 'test_set',
            Uuid::fromHexToBytes($duplicateB) => 'test_set',
        ]);

        $this->persister->sync($this->loadFixtureSingleSet(), $appId, Context::createDefaultContext());

        $deletes = $this->setRepository->getPayloads(StaticEntityRepository::DELETE);
        static::assertEqualsCanonicalizing(
            [['id' => $duplicateA], ['id' => $duplicateB]],
            $deletes
        );

        // the set is treated as new again
        $upserts = $this->setRepository->getPayloads(StaticEntityRepository::UPSERT);
        static::assertCount(1, $upserts);
        static::assertSame('test_set', $upserts[0]['name']);
    }

    public function testRemoveByNamesDeletesResolvedIds(): void
    {
        $idA = Uuid::randomHex();
        $idB = Uuid::randomHex();

        $this->connection->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturnCallback(function (string $sql, array $params, array $types) use ($idA, $idB): array {
                static::assertStringContainsString('FROM custom_field_set WHERE name IN (:names)', $sql);
                static::assertSame(['names' => ['set_a', 'set_b']], $params);
                static::assertSame(['names' => ArrayParameterType::STRING], $types);

                return [$idA, $idB];
            });

        $this->persister->removeByNames(['set_a', 'set_b'], Context::createDefaultContext());

        static::assertSame(
            [['id' => $idA], ['id' => $idB]],
            $this->setRepository->getPayloads(StaticEntityRepository::DELETE)
        );
    }

    public function testRemoveByNamesWithEmptyInputIsNoop(): void
    {
        $this->connection->expects($this->never())->method('fetchFirstColumn');

        $this->persister->removeByNames([], Context::createDefaultContext());

        static::assertSame([], $this->setRepository->deletes);
    }

    public function testRemoveByNamesWithoutMatchesDoesNotDelete(): void
    {
        $this->connection->expects($this->once())->method('fetchFirstColumn')->willReturn([]);

        $this->persister->removeByNames(['unknown'], Context::createDefaultContext());

        static::assertSame([], $this->setRepository->deletes);
    }

    private function loadFixture(): CustomFields
    {
        return CustomFieldXmlLoader::load(__DIR__ . '/_fixtures/custom-fields.xml');
    }

    private function loadFixtureSingleSet(): CustomFields
    {
        // reuse the first set of the shared fixture by loading and slicing
        $customFields = $this->loadFixture();

        return CustomFields::fromArray(['customFieldSets' => [$customFields->getCustomFieldSets()[0]]]);
    }
}

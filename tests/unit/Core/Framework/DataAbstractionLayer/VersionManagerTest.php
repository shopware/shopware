<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommit\VersionCommitDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\Aggregate\VersionCommitData\VersionCommitDataDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Version\VersionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\VersionManager;
use Shopware\Core\Framework\DataAbstractionLayer\Write\CloneBehavior;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Struct\ArrayEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[CoversClass(VersionManager::class)]
class VersionManagerTest extends TestCase
{
    private VersionManager $versionManager;

    public function testCloneEntityWithFkAsExtension(): void
    {
        $entityReaderMock = $this->createMock(EntityReaderInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $entityWriterMock = $this->createMock(EntityWriterInterface::class);

        $this->versionManager = $this->createVersionManager([
            'entityWriter' => $entityWriterMock,
            'entityReader' => $entityReaderMock,
            'serializer' => $serializer,
        ]);

        $entityCollectionMock = new EntityCollection([
            (new Entity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]);

        $entityReaderMock->expects($this->once())->method('read')->willReturn($entityCollectionMock);
        $serializer->expects($this->once())->method('serialize')
            ->willReturn('{"extensions":{"foreignKeys":{"extensions":[],"apiAlias":null,"manyToOneId":"' . Uuid::randomHex() . '"}}}');

        $writeContextMock = $this->createMock(WriteContext::class);

        $writeContextMockWithVersionId = $this->createMock(WriteContext::class);
        $writeContextMock->expects($this->once())->method('createWithVersionId')->willReturn($writeContextMockWithVersionId);

        $entityWriterMock->expects($this->once())->method('insert')->willReturn([
            'product' => [
                new EntityWriteResult('1', ['languageId' => '1'], 'product', EntityWriteResult::OPERATION_INSERT),
            ],
        ]);

        $writeContextMockWithVersionId->expects($this->once())->method('scope')
            ->with(static::equalTo(Context::SYSTEM_SCOPE), static::callback(static function (callable $closure) use ($writeContextMockWithVersionId) {
                /** @var callable(MockObject&WriteContext): void $closure */
                $closure($writeContextMockWithVersionId);

                return true;
            }));

        $writeContextMockWithVersionId->expects($this->any())->method('getContext')->willReturn(Context::createDefaultContext());

        $registry = new StaticDefinitionInstanceRegistry(
            [
                VersionManagerTestDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $entityWriteResult = $this->versionManager->clone(
            $registry->getByEntityName('product'),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            $writeContextMock,
            $this->createMock(CloneBehavior::class)
        );

        static::assertNotEmpty($entityWriteResult);
        static::assertSame('insert', $entityWriteResult['product'][0]->getOperation());
        static::assertSame('product', $entityWriteResult['product'][0]->getEntityName());
    }

    public function testCloneEntityNotExist(): void
    {
        $entityReaderMock = $this->createMock(EntityReaderInterface::class);
        $entityReaderMock->expects($this->once())->method('read')->willReturn(new EntityCollection([]));

        $this->versionManager = $this->createVersionManager([
            'entityReader' => $entityReaderMock,
        ]);

        $productId = 'product-id';
        $this->expectExceptionObject(DataAbstractionLayerException::cannotCreateNewVersion('product', $productId));

        $registry = new StaticDefinitionInstanceRegistry(
            [
                VersionManagerTestDefinition::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $this->versionManager->clone(
            $registry->getByEntityName('product'),
            $productId,
            Uuid::randomHex(),
            Uuid::randomHex(),
            $this->createMock(WriteContext::class),
            $this->createMock(CloneBehavior::class)
        );
    }

    public function testMergeEntityWithLockedVersion(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);

        $registry = new StaticDefinitionInstanceRegistry(
            [],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(false);
        $lockFactory->expects($this->once())->method('createLock')->willReturn($lock);

        $this->versionManager = $this->createVersionManager([
            'registry' => $registry,
            'lockFactory' => $lockFactory,
        ]);

        $versionId = 'version-id';
        $this->expectExceptionObject(DataAbstractionLayerException::versionMergeAlreadyLocked($versionId));

        $this->versionManager->merge(
            $versionId,
            $this->createMock(WriteContext::class)
        );
    }

    public function testMergeFailsForNonExistentVersion(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);
        $lock = $this->createMock(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lockFactory->method('createLock')->willReturn($lock);

        $entitySearcherMock = $this->createMock(EntitySearcherInterface::class);

        $entitySearcherMock->method('search')->willReturn(
            new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext())
        );

        $versionManager = $this->createVersionManager([
            'entitySearcher' => $entitySearcherMock,
            'lockFactory' => $lockFactory,
        ]);

        $versionId = 'non-existent-version-id';

        $this->expectExceptionObject(DataAbstractionLayerException::versionNotExists($versionId));

        $versionManager->merge($versionId, $this->createMock(WriteContext::class));
    }

    /**
     * Cases whose expected output is identical under flag-on (new mergeOverwrites)
     * and flag-off (legacy array_replace_recursive). Safe to run under either flag state.
     *
     * @param class-string<EntityDefinition> $definitionClass
     * @param array<string, mixed> $originalData
     * @param array<string, mixed> $overwriteData
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('cloneOverwriteCompatibleProvider')]
    public function testCloneEntityWithCompatibleOverwrites(
        string $definitionClass,
        string $entityName,
        array $originalData,
        array $overwriteData,
        array $expectedData
    ): void {
        $this->assertCloneOverwriteResult($definitionClass, $entityName, $originalData, $overwriteData, $expectedData);
    }

    /**
     * ListField cases — under v6.8.0.0 the new mergeOverwrites fully replaces ListField
     * values rather than index-merging them. Unit tests default to v6.8.0.0 active.
     *
     * @param class-string<EntityDefinition> $definitionClass
     * @param array<string, mixed> $originalData
     * @param array<string, mixed> $overwriteData
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('cloneOverwriteListFieldProvider')]
    public function testCloneEntityWithListFieldOverwrites(
        string $definitionClass,
        string $entityName,
        array $originalData,
        array $overwriteData,
        array $expectedData
    ): void {
        $this->assertCloneOverwriteResult($definitionClass, $entityName, $originalData, $overwriteData, $expectedData);
    }

    /**
     * Legacy regression: with v6.8.0.0 disabled, ListField values still index-merge
     * via array_replace_recursive (the pre-6.8 behaviour).
     *
     * @param class-string<EntityDefinition> $definitionClass
     * @param array<string, mixed> $originalData
     * @param array<string, mixed> $overwriteData
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('cloneOverwriteListFieldLegacyProvider')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testCloneEntityWithListFieldOverwritesLegacy(
        string $definitionClass,
        string $entityName,
        array $originalData,
        array $overwriteData,
        array $expectedData
    ): void {
        $this->assertCloneOverwriteResult($definitionClass, $entityName, $originalData, $overwriteData, $expectedData);
    }

    /**
     * @return iterable<string, array{definitionClass: class-string<EntityDefinition>, entityName: string, originalData: array<string, mixed>, overwriteData: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function cloneOverwriteCompatibleProvider(): iterable
    {
        // Array merge behavior tests - matching array_replace_recursive behavior
        yield 'indexed array replaced by index' => [
            'definitionClass' => VersionManagerArrayTestDefinition::class,
            'entityName' => 'test_array_entity',
            'originalData' => ['indexedArray' => ['a', 'b', 'c']],
            'overwriteData' => ['indexedArray' => ['x']],
            // array_replace_recursive merges by index: 'x' replaces 'a', 'b' and 'c' remain
            'expectedData' => ['indexedArray' => ['x', 'b', 'c']],
        ];

        yield 'empty array in overwrite' => [
            'definitionClass' => VersionManagerArrayTestDefinition::class,
            'entityName' => 'test_array_entity',
            'originalData' => ['emptyArray' => ['a', 'b']],
            'overwriteData' => ['emptyArray' => []],
            // array_replace_recursive with empty array keeps original (empty array doesn't replace)
            'expectedData' => ['emptyArray' => ['a', 'b']],
        ];

        yield 'mixed numeric keys not sequential' => [
            'definitionClass' => VersionManagerArrayTestDefinition::class,
            'entityName' => 'test_array_entity',
            'originalData' => ['mixedArray' => [0 => 'a', 2 => 'b', 5 => 'c']],
            'overwriteData' => ['mixedArray' => [0 => 'x', 2 => 'y']],
            // array_replace_recursive merges by key, preserving keys not in overwrite
            'expectedData' => ['mixedArray' => [0 => 'x', 2 => 'y', 5 => 'c']],
        ];

        yield 'nested indexed arrays' => [
            'definitionClass' => VersionManagerArrayTestDefinition::class,
            'entityName' => 'test_array_entity',
            'originalData' => ['nested' => [['a', 'b'], ['c', 'd']]],
            'overwriteData' => ['nested' => [['x']]],
            // array_replace_recursive recursively merges: [['x', 'b'], ['c', 'd']]
            'expectedData' => ['nested' => [['x', 'b'], ['c', 'd']]],
        ];

        yield 'nested associative arrays' => [
            'definitionClass' => VersionManagerArrayTestDefinition::class,
            'entityName' => 'test_array_entity',
            'originalData' => ['nested' => ['level1' => ['level2' => 'original']]],
            'overwriteData' => ['nested' => ['level1' => ['level2' => 'updated', 'new' => 'value']]],
            'expectedData' => ['nested' => ['level1' => ['level2' => 'updated', 'new' => 'value']]],
        ];

        yield 'new field added when key does not exist' => [
            'definitionClass' => VersionManagerArrayTestDefinition::class,
            'entityName' => 'test_array_entity',
            'originalData' => ['existingField' => 'value'],
            'overwriteData' => ['newField' => 'newValue'],
            'expectedData' => ['existingField' => 'value', 'newField' => 'newValue'],
        ];

        yield 'scalar replaces array' => [
            'definitionClass' => VersionManagerArrayTestDefinition::class,
            'entityName' => 'test_array_entity',
            'originalData' => ['field' => ['old' => 'array']],
            'overwriteData' => ['field' => 'scalar'],
            'expectedData' => ['field' => 'scalar'],
        ];

        yield 'array replaces scalar' => [
            'definitionClass' => VersionManagerArrayTestDefinition::class,
            'entityName' => 'test_array_entity',
            'originalData' => ['field' => 'scalar'],
            'overwriteData' => ['field' => ['new' => 'array']],
            'expectedData' => ['field' => ['new' => 'array']],
        ];

        yield 'null value replaces existing value' => [
            'definitionClass' => VersionManagerArrayTestDefinition::class,
            'entityName' => 'test_array_entity',
            'originalData' => ['field' => 'value'],
            'overwriteData' => ['field' => null],
            'expectedData' => ['field' => null],
        ];

        yield 'nested fields with complex structure' => [
            'definitionClass' => VersionManagerNestedFieldTestDefinition::class,
            'entityName' => 'test_nested_entity',
            'originalData' => [
                'simpleField' => 'simple',
                'nestedData' => ['nested1' => 'value1', 'nested2' => 'value2'],
                'deeplyNested' => ['level1' => ['level2' => ['level3' => 'deep']]],
            ],
            'overwriteData' => [
                'nestedData' => ['nested1' => 'updated'],
            ],
            // array_replace_recursive merges nested associative arrays
            'expectedData' => [
                'simpleField' => 'simple',
                'nestedData' => ['nested1' => 'updated', 'nested2' => 'value2'],
                'deeplyNested' => ['level1' => ['level2' => ['level3' => 'deep']]],
            ],
        ];

        yield 'nested field with undefined key in overwrite' => [
            'definitionClass' => VersionManagerNestedFieldTestDefinition::class,
            'entityName' => 'test_nested_entity',
            'originalData' => [
                'nestedData' => ['nested1' => 'value1', 'nested2' => 'value2'],
            ],
            'overwriteData' => [
                'nestedData' => ['undefinedKey' => 'newValue', 'nested1' => 'updated'],
            ],
            // When a key  doesn't have a field definition in the nested fields, it should still be merged
            'expectedData' => [
                'nestedData' => ['nested1' => 'updated', 'nested2' => 'value2', 'undefinedKey' => 'newValue'],
            ],
        ];
    }

    /**
     * Cases asserting the v6.8.0.0 behaviour: ListField values are fully replaced.
     *
     * @return iterable<string, array{definitionClass: class-string<EntityDefinition>, entityName: string, originalData: array<string, mixed>, overwriteData: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function cloneOverwriteListFieldProvider(): iterable
    {
        yield 'ListField is replaced, not merged' => [
            'definitionClass' => VersionManagerListFieldTestDefinition::class,
            'entityName' => 'test_entity',
            'originalData' => ['listField' => ['value1', 'value2', 'value3']],
            'overwriteData' => ['listField' => ['value2']],
            'expectedData' => ['listField' => ['value2']],
        ];

        yield 'nested ListField inside JsonField is replaced, not merged' => [
            'definitionClass' => VersionManagerNestedListFieldTestDefinition::class,
            'entityName' => 'test_nested_list_entity',
            'originalData' => [
                'container' => [
                    'name' => 'Container Name',
                    'items' => ['item1', 'item2', 'item3'],
                    'metadata' => 'some metadata',
                ],
            ],
            'overwriteData' => [
                'container' => [
                    'items' => ['newItem'],
                ],
            ],
            'expectedData' => [
                'container' => [
                    'name' => 'Container Name',
                    'items' => ['newItem'],
                    'metadata' => 'some metadata',
                ],
            ],
        ];
    }

    /**
     * Cases asserting the pre-6.8 behaviour: ListField values index-merge via array_replace_recursive.
     *
     * @return iterable<string, array{definitionClass: class-string<EntityDefinition>, entityName: string, originalData: array<string, mixed>, overwriteData: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function cloneOverwriteListFieldLegacyProvider(): iterable
    {
        yield 'ListField index-merged (legacy)' => [
            'definitionClass' => VersionManagerListFieldTestDefinition::class,
            'entityName' => 'test_entity',
            'originalData' => ['listField' => ['value1', 'value2', 'value3']],
            'overwriteData' => ['listField' => ['value2']],
            'expectedData' => ['listField' => ['value2', 'value2', 'value3']],
        ];

        yield 'nested ListField index-merged (legacy)' => [
            'definitionClass' => VersionManagerNestedListFieldTestDefinition::class,
            'entityName' => 'test_nested_list_entity',
            'originalData' => [
                'container' => [
                    'name' => 'Container Name',
                    'items' => ['item1', 'item2', 'item3'],
                    'metadata' => 'some metadata',
                ],
            ],
            'overwriteData' => [
                'container' => [
                    'items' => ['newItem'],
                ],
            ],
            'expectedData' => [
                'container' => [
                    'name' => 'Container Name',
                    'items' => ['newItem', 'item2', 'item3'],
                    'metadata' => 'some metadata',
                ],
            ],
        ];
    }

    /**
     * @param class-string<EntityDefinition> $definitionClass
     * @param array<string, mixed> $originalData
     * @param array<string, mixed> $overwriteData
     * @param array<string, mixed> $expectedData
     */
    private function assertCloneOverwriteResult(
        string $definitionClass,
        string $entityName,
        array $originalData,
        array $overwriteData,
        array $expectedData
    ): void {
        $entityReaderMock = $this->createMock(EntityReaderInterface::class);
        $serializer = $this->createMock(SerializerInterface::class);
        $entityWriterMock = $this->createMock(EntityWriterInterface::class);

        $registry = new StaticDefinitionInstanceRegistry(
            [$definitionClass],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        $this->versionManager = $this->createVersionManager([
            'entityWriter' => $entityWriterMock,
            'entityReader' => $entityReaderMock,
            'serializer' => $serializer,
            'registry' => $registry,
        ]);

        $entityId = Uuid::randomHex();
        $entity = new ArrayEntity(array_merge(['id' => $entityId], $originalData));
        $entityReaderMock->expects($this->once())->method('read')->willReturn(new EntityCollection([$entity]));
        $serializer->expects($this->once())->method('serialize')->willReturn(json_encode(array_merge(['id' => $entityId], $originalData), \JSON_THROW_ON_ERROR));

        $writeContextMock = $this->createMock(WriteContext::class);
        $writeContextMockWithVersionId = $this->createMock(WriteContext::class);
        $writeContextMock->expects($this->once())->method('createWithVersionId')->willReturn($writeContextMockWithVersionId);
        $writeContextMockWithVersionId->expects($this->atLeastOnce())->method('getContext')->willReturn(Context::createDefaultContext());

        $insertedData = null;
        $entityWriterMock->expects($this->once())->method('insert')
            ->willReturnCallback(function ($definition, $data) use (&$insertedData, $entityName) {
                $insertedData = $data;

                return [$entityName => [new EntityWriteResult('1', [], $entityName, EntityWriteResult::OPERATION_INSERT)]];
            });

        $writeContextMockWithVersionId->expects($this->once())->method('scope')
            ->with(Context::SYSTEM_SCOPE, static::callback(function (callable $closure) use ($writeContextMockWithVersionId) {
                $closure($writeContextMockWithVersionId);

                return true;
            }));

        $this->versionManager->clone(
            $registry->getByEntityName($entityName),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            $writeContextMock,
            new CloneBehavior($overwriteData)
        );

        static::assertNotNull($insertedData);

        // Assert that all expected keys exist with correct values
        foreach ($expectedData as $key => $expectedValue) {
            static::assertArrayHasKey($key, $insertedData[0], "Key '$key' should exist");
            static::assertSame($expectedValue, $insertedData[0][$key], "Failed for key '$key'");
        }

        // Assert that insertedData doesn't contain extra keys beyond expectedData and 'id'
        $allowedKeys = array_merge(array_keys($expectedData), ['id', 'createdAt', 'updatedAt']);
        foreach (array_keys($insertedData[0]) as $key) {
            static::assertContains($key, $allowedKeys, "Unexpected key '$key' found in insertedData");
        }
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createVersionManager(array $overrides = []): VersionManager
    {
        $defaults = [
            'entityWriter' => $this->createMock(EntityWriterInterface::class),
            'entityReader' => $this->createMock(EntityReaderInterface::class),
            'entitySearcher' => $this->createMock(EntitySearcherInterface::class),
            'entityWriteGateway' => $this->createMock(EntityWriteGatewayInterface::class),
            'eventDispatcher' => $this->createMock(EventDispatcherInterface::class),
            'serializer' => $this->createMock(SerializerInterface::class),
            'registry' => $this->createMock(DefinitionInstanceRegistry::class),
            'versionCommitDefinition' => $this->createMock(VersionCommitDefinition::class),
            'versionCommitDataDefinition' => $this->createMock(VersionCommitDataDefinition::class),
            'versionDefinition' => $this->createMock(VersionDefinition::class),
            'lockFactory' => $this->createMock(LockFactory::class),
            'clock' => new NativeClock(),
        ];

        $params = array_merge($defaults, $overrides);

        return new VersionManager(
            $params['entityWriter'],
            $params['entityReader'],
            $params['entitySearcher'],
            $params['entityWriteGateway'],
            $params['eventDispatcher'],
            $params['serializer'],
            $params['registry'],
            $params['versionCommitDefinition'],
            $params['versionCommitDataDefinition'],
            $params['versionDefinition'],
            $params['lockFactory'],
            $params['clock']
        );
    }
}

/**
 * @internal
 */
class VersionManagerTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'product';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
        ]);
    }
}

/**
 * @internal
 */
class VersionManagerListFieldTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_entity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new ListField('list_field', 'listField'),
        ]);
    }
}

/**
 * @internal
 */
class VersionManagerArrayTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_array_entity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new JsonField('indexedArray', 'indexedArray'),
            new JsonField('emptyArray', 'emptyArray'),
            new JsonField('mixedArray', 'mixedArray'),
            new JsonField('nested', 'nested'),
            new StringField('scalarField', 'scalarField'),
            new StringField('existingField', 'existingField'),
            new StringField('newField', 'newField'),
            new JsonField('field', 'field'),
        ]);
    }
}

/**
 * @internal
 */
class VersionManagerNestedFieldTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_nested_entity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new StringField('simple_field', 'simpleField'),
            new JsonField('nested_data', 'nestedData', [
                new StringField('nested1', 'nested1'),
                new StringField('nested2', 'nested2'),
            ]),
            new JsonField('deeply_nested', 'deeplyNested', [
                new JsonField('level1', 'level1', [
                    new JsonField('level2', 'level2', [
                        new StringField('level3', 'level3'),
                    ]),
                ]),
            ]),
        ]);
    }
}

/**
 * @internal
 */
class VersionManagerNestedListFieldTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'test_nested_list_entity';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new JsonField('container', 'container', [
                new StringField('name', 'name'),
                new ListField('items', 'items'),
                new StringField('metadata', 'metadata'),
            ]),
        ]);
    }
}

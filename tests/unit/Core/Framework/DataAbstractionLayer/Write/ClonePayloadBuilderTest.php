<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer\Write;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\CloneProtection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Extension;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ResetOnClone;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\WriteProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\WasModifiedByUserField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\MappingEntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Write\ClonePayloadBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Framework\Validation\WriteConstraintViolationException;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ClonePayloadBuilder::class)]
class ClonePayloadBuilderTest extends TestCase
{
    private const NOW = '2026-10-28 12:00:00';

    private ClonePayloadBuilder $builder;

    private StaticDefinitionInstanceRegistry $registry;

    protected function setUp(): void
    {
        $this->builder = new ClonePayloadBuilder(new MockClock(self::NOW));

        $this->registry = new StaticDefinitionInstanceRegistry(
            [
                ClonePayloadOrderTestDefinition::class,
                ClonePayloadAddressTestDefinition::class,
                ClonePayloadDeliveryTestDefinition::class,
                ClonePayloadDetailTestDefinition::class,
                ClonePayloadNoteTestDefinition::class,
                ClonePayloadTagTestDefinition::class,
                ClonePayloadOrderTagTestDefinition::class,
                ClonePayloadProtectedRootTestDefinition::class,
                ClonePayloadProtectedChildTestDefinition::class,
                ClonePayloadForestTestDefinition::class,
                ClonePayloadTreeTestDefinition::class,
                ClonePayloadTranslatedTestDefinition::class,
                ClonePayloadTranslationTestDefinition::class,
                LanguageDefinition::class,
                ClonePayloadResetOnCloneTestDefinition::class,
                ClonePayloadRequiredUpdatedAtTestDefinition::class,
                ClonePayloadWriteProtectedTestDefinition::class,
                ClonePayloadListFieldTestDefinition::class,
                ClonePayloadArrayTestDefinition::class,
                ClonePayloadNestedFieldTestDefinition::class,
                ClonePayloadNestedListFieldTestDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
    }

    public function testACopyPointsReferencesBetweenClonedRowsAtTheClone(): void
    {
        $sourceId = Uuid::randomHex();
        $sourceAddressId = Uuid::randomHex();
        $sourceDeliveryId = Uuid::randomHex();

        $payload = $this->build('clone_order', $this->orderLikeSource($sourceId, $sourceAddressId, $sourceDeliveryId), $sourceId, Uuid::randomHex());

        $clonedAddressId = $payload['addresses'][0]['id'];
        static::assertNotSame($sourceAddressId, $clonedAddressId);
        static::assertNotSame($sourceDeliveryId, $payload['deliveries'][0]['id']);

        // The root and the delivery referenced the source address; both reference the cloned address now.
        static::assertSame($clonedAddressId, $payload['billingAddressId']);
        static::assertSame($clonedAddressId, $payload['deliveries'][0]['shippingAddressId']);

        // The reference back to the root is left to the writer.
        static::assertArrayNotHasKey('orderId', $payload['deliveries'][0]);
    }

    public function testAVersionKeepsIdsAndReferences(): void
    {
        $sourceId = Uuid::randomHex();
        $sourceAddressId = Uuid::randomHex();
        $sourceDeliveryId = Uuid::randomHex();

        $payload = $this->build('clone_order', $this->orderLikeSource($sourceId, $sourceAddressId, $sourceDeliveryId), $sourceId, $sourceId);

        static::assertSame($sourceId, $payload['id']);
        static::assertSame($sourceAddressId, $payload['addresses'][0]['id']);
        static::assertSame($sourceDeliveryId, $payload['deliveries'][0]['id']);
        static::assertSame($sourceAddressId, $payload['billingAddressId']);
        static::assertSame($sourceAddressId, $payload['deliveries'][0]['shippingAddressId']);
        static::assertSame($sourceId, $payload['deliveries'][0]['orderId']);
    }

    public function testAReferenceToARowOutsideTheCloneIsKept(): void
    {
        $sourceId = Uuid::randomHex();
        $foreignAddressId = Uuid::randomHex();

        $payload = $this->build('clone_order', [
            'id' => $sourceId,
            'billingAddressId' => $foreignAddressId,
            'addresses' => [['id' => Uuid::randomHex(), 'orderId' => $sourceId]],
        ], $sourceId, Uuid::randomHex());

        static::assertSame($foreignAddressId, $payload['billingAddressId']);
    }

    public function testAReferenceThroughAOneToOneRowIsRemapped(): void
    {
        $sourceId = Uuid::randomHex();
        $sourceAddressId = Uuid::randomHex();

        $payload = $this->build('clone_order', [
            'id' => $sourceId,
            'addresses' => [['id' => $sourceAddressId, 'orderId' => $sourceId]],
            'detail' => ['id' => Uuid::randomHex(), 'orderId' => $sourceId, 'noteAddressId' => $sourceAddressId],
        ], $sourceId, Uuid::randomHex());

        static::assertSame($payload['addresses'][0]['id'], $payload['detail']['noteAddressId']);
        static::assertArrayNotHasKey('orderId', $payload['detail']);
    }

    public function testAForeignKeyExtensionIsCopiedAndRemapped(): void
    {
        $sourceId = Uuid::randomHex();
        $sourceAddressId = Uuid::randomHex();

        $payload = $this->build('clone_order', [
            'id' => $sourceId,
            'addresses' => [['id' => $sourceAddressId, 'orderId' => $sourceId]],
            'extensions' => ['foreignKeys' => ['extRefId' => $sourceAddressId, 'unknown' => Uuid::randomHex()]],
        ], $sourceId, Uuid::randomHex());

        static::assertSame($payload['addresses'][0]['id'], $payload['extRefId']);
        static::assertArrayNotHasKey('unknown', $payload);
    }

    public function testAnExtensionAssociationIsClonedUnderExtensionsAndRemapped(): void
    {
        $sourceId = Uuid::randomHex();
        $sourceAddressId = Uuid::randomHex();

        $payload = $this->build('clone_order', [
            'id' => $sourceId,
            'addresses' => [['id' => $sourceAddressId, 'orderId' => $sourceId]],
            'extensions' => [
                'extDetails' => [['id' => Uuid::randomHex(), 'orderId' => $sourceId, 'noteAddressId' => $sourceAddressId]],
            ],
        ], $sourceId, Uuid::randomHex());

        $extDetail = $payload['extensions']['extDetails'][0];
        static::assertNotSame($sourceAddressId, $extDetail['noteAddressId']);
        static::assertSame($payload['addresses'][0]['id'], $extDetail['noteAddressId']);
    }

    public function testARequiredUpdatedAtIsSetOnTheClone(): void
    {
        $sourceId = Uuid::randomHex();

        $payload = $this->build('clone_required_updated_at', ['id' => $sourceId, 'updatedAt' => '2021-01-01 00:00:00'], $sourceId, Uuid::randomHex());

        static::assertInstanceOf(\DateTimeInterface::class, $payload['updatedAt']);
        static::assertSame(self::NOW, $payload['updatedAt']->format('Y-m-d H:i:s'));
    }

    public function testACascadeFlagOnAScalarFieldChangesNothing(): void
    {
        $sourceId = Uuid::randomHex();

        $payload = $this->build('clone_order', ['id' => $sourceId, 'comment' => 'copied like any scalar'], $sourceId, Uuid::randomHex());
        static::assertSame('copied like any scalar', $payload['comment']);

        $criteria = new Criteria();
        $this->builder->addCloneAssociations($this->registry->getByEntityName('clone_order'), $criteria, true, Context::createDefaultContext());
        static::assertFalse($criteria->hasAssociation('comment'));
    }

    public function testANestedListWhoseRowsFilterToNothingIsLeftOut(): void
    {
        $sourceId = Uuid::randomHex();

        // The only value of the row is the reference back to the root, which the writer sets itself.
        $payload = $this->build('clone_order', [
            'id' => $sourceId,
            'addresses' => [['orderId' => $sourceId]],
        ], $sourceId, Uuid::randomHex());

        static::assertArrayNotHasKey('addresses', $payload);
    }

    public function testATranslationOfANestedRowLosesItsEntityIdToo(): void
    {
        $sourceId = Uuid::randomHex();
        $labelId = Uuid::randomHex();
        $languageId = Uuid::randomHex();

        $payload = $this->build('clone_order', [
            'id' => $sourceId,
            'labels' => [[
                'id' => $labelId,
                'orderId' => $sourceId,
                'translations' => [['cloneTranslatedId' => $labelId, 'languageId' => $languageId, 'name' => 'Label']],
            ]],
        ], $sourceId, Uuid::randomHex());

        static::assertNotSame($labelId, $payload['labels'][0]['id']);
        static::assertEquals([['languageId' => $languageId, 'name' => 'Label']], $payload['labels'][0]['translations']);
    }

    public function testFieldsTheWriterOwnsAreLeftOut(): void
    {
        $sourceId = Uuid::randomHex();

        $payload = $this->build('clone_order', [
            'id' => $sourceId,
            'versionId' => Uuid::randomHex(),
            'wasModifiedByUser' => true,
            'billingAddressId' => null,
            'billingAddress' => ['id' => Uuid::randomHex()],
            'notes' => [['id' => Uuid::randomHex(), 'orderId' => $sourceId]],
            'addresses' => [],
            'tags' => [],
        ], $sourceId, Uuid::randomHex());

        static::assertArrayNotHasKey('versionId', $payload, 'version fields are set by the version context');
        static::assertArrayNotHasKey('wasModifiedByUser', $payload, 'derived by the serializer');
        static::assertArrayNotHasKey('billingAddressId', $payload, 'null values are left to the defaults');
        static::assertArrayNotHasKey('billingAddress', $payload, 'many-to-one rows belong to another entity');
        static::assertArrayNotHasKey('notes', $payload, 'not clone relevant');
        static::assertArrayNotHasKey('addresses', $payload, 'an empty list adds nothing');
        static::assertArrayNotHasKey('tags', $payload, 'an empty many-to-many list adds nothing');
    }

    public function testManyToManyRowsAreReferencedByIdOnly(): void
    {
        $sourceId = Uuid::randomHex();
        $tagId = Uuid::randomHex();

        $payload = $this->build('clone_order', [
            'id' => $sourceId,
            'tags' => [['id' => $tagId, 'name' => 'Tag']],
        ], $sourceId, Uuid::randomHex());

        static::assertSame([['id' => $tagId]], $payload['tags']);
    }

    public function testATranslationKeepsItsLanguageAndLosesItsEntityId(): void
    {
        $sourceId = Uuid::randomHex();
        $languageId = Uuid::randomHex();

        $payload = $this->build('clone_translated', [
            'id' => $sourceId,
            'translations' => [['cloneTranslatedId' => $sourceId, 'languageId' => $languageId, 'name' => 'Name']],
        ], $sourceId, Uuid::randomHex());

        static::assertEquals([['languageId' => $languageId, 'name' => 'Name']], $payload['translations']);
    }

    public function testAParentReferenceIsDroppedOnACopyAndKeptOnAVersion(): void
    {
        $sourceId = Uuid::randomHex();
        $parentId = Uuid::randomHex();
        $source = ['id' => $sourceId, 'parentId' => $parentId];

        static::assertArrayNotHasKey('parentId', $this->build('clone_tree', $source, $sourceId, Uuid::randomHex()));
        static::assertSame($parentId, $this->build('clone_tree', $source, $sourceId, $sourceId)['parentId']);
    }

    public function testACloneProtectedAssociationIsLeftOut(): void
    {
        $sourceId = Uuid::randomHex();

        Context::createDefaultContext()->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($sourceId): void {
            $payload = $this->builder->build(
                $this->registry->getByEntityName('clone_protected_root'),
                [
                    'id' => $sourceId,
                    'children' => [['id' => Uuid::randomHex(), 'parentId' => $sourceId]],
                ],
                $sourceId,
                Uuid::randomHex(),
                [],
                $context,
            );

            static::assertArrayNotHasKey('children', $payload);
        });
    }

    public function testCloneProtectionDependsOnTheScope(): void
    {
        $definition = $this->registry->getByEntityName('clone_protected_child');

        static::assertFalse($this->builder->isCloneProtected($definition, Context::createDefaultContext()), 'the default context runs in the system scope');

        Context::createDefaultContext()->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($definition): void {
            static::assertTrue($this->builder->isCloneProtected($definition, $context));
        });
    }

    public function testTimestampsAreResetOnTheClone(): void
    {
        $sourceId = Uuid::randomHex();

        $payload = $this->build('clone_order', [
            'id' => $sourceId,
            'createdAt' => '2020-01-01 00:00:00',
            'updatedAt' => '2021-01-01 00:00:00',
        ], $sourceId, Uuid::randomHex());

        static::assertInstanceOf(\DateTimeInterface::class, $payload['createdAt']);
        static::assertSame(self::NOW, $payload['createdAt']->format('Y-m-d H:i:s'));
        static::assertNull($payload['updatedAt']);
    }

    public function testAWriteProtectedFieldIsNotCopied(): void
    {
        $sourceId = Uuid::randomHex();

        $payload = $this->build('clone_write_protected', [
            'id' => $sourceId,
            'secret' => 'never writable',
            'token' => 'writable by the system',
        ], $sourceId, Uuid::randomHex());

        static::assertArrayNotHasKey('secret', $payload);
        static::assertSame('writable by the system', $payload['token']);
    }

    public function testAnOverwriteOfAWriteProtectedFieldIsRejected(): void
    {
        $sourceId = Uuid::randomHex();

        Context::createDefaultContext()->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($sourceId): void {
            try {
                $this->builder->build(
                    $this->registry->getByEntityName('clone_write_protected'),
                    ['id' => $sourceId],
                    $sourceId,
                    Uuid::randomHex(),
                    ['token' => 'forced'],
                    $context,
                );
            } catch (WriteConstraintViolationException $exception) {
                static::assertSame('/token', $exception->getPath());
                static::assertCount(1, $exception->getViolations());

                return;
            }

            static::fail('The overwrite of a write-protected field was accepted');
        });
    }

    public function testCloneAssociationsFollowOnlyCloneRelevantToManyAssociations(): void
    {
        $criteria = new Criteria();

        $this->builder->addCloneAssociations($this->registry->getByEntityName('clone_order'), $criteria, true, Context::createDefaultContext());

        $associations = array_keys($criteria->getAssociations());
        sort($associations);

        // Every clone-relevant cascade is read, notes is not clone relevant.
        static::assertSame(['addresses', 'billingAddress', 'deliveries', 'detail', 'extDetails', 'labels', 'tags'], $associations);

        // Only to-many rows are followed further; the address rows are read through the order, not the delivery.
        static::assertSame([], $criteria->getAssociation('billingAddress')->getAssociations());
        static::assertSame([], $criteria->getAssociation('tags')->getAssociations());
    }

    public function testCloneAssociationsSkipACloneProtectedReference(): void
    {
        $criteria = new Criteria();

        Context::createDefaultContext()->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($criteria): void {
            $this->builder->addCloneAssociations($this->registry->getByEntityName('clone_protected_root'), $criteria, true, $context);
        });

        static::assertFalse($criteria->hasAssociation('children'));
    }

    public function testCloneAssociationsReadATreeFromItsRootsAndBoundTheDepth(): void
    {
        $criteria = new Criteria();

        $this->builder->addCloneAssociations($this->registry->getByEntityName('clone_forest'), $criteria, true, Context::createDefaultContext());

        $trees = $criteria->getAssociation('trees');
        static::assertEquals([new EqualsFilter('clone_tree.parentId', null)], $trees->getFilters());

        $depth = 0;
        $level = $trees;
        while ($level->hasAssociation('children')) {
            $level = $level->getAssociation('children');
            ++$depth;
        }

        static::assertSame(29, $depth);
    }

    public function testCloneAssociationsLeaveChildrenOutWhenTheyAreNotWanted(): void
    {
        $criteria = new Criteria();

        $this->builder->addCloneAssociations($this->registry->getByEntityName('clone_forest'), $criteria, false, Context::createDefaultContext());

        static::assertFalse($criteria->getAssociation('trees')->hasAssociation('children'));
    }

    /**
     * @param array<string, mixed> $originalData
     * @param array<string, mixed> $overwriteData
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('cloneOverwriteCompatibleProvider')]
    public function testOverwritesAreMergedIntoTheClone(string $entityName, array $originalData, array $overwriteData, array $expectedData): void
    {
        $this->assertOverwriteResult($entityName, $originalData, $overwriteData, $expectedData);
    }

    public function testAResetOnCloneFieldIsLeftOut(): void
    {
        $this->assertOverwriteResult(
            'clone_reset_on_clone',
            ['name' => 'Original', 'resetValue' => 'Derived from original'],
            [],
            ['name' => 'Original']
        );
    }

    public function testAResetOnCloneFieldAcceptsAnExplicitOverwrite(): void
    {
        $this->assertOverwriteResult(
            'clone_reset_on_clone',
            ['name' => 'Original', 'resetValue' => 'Derived from original'],
            ['resetValue' => 'Explicit overwrite'],
            ['name' => 'Original', 'resetValue' => 'Explicit overwrite']
        );
    }

    /**
     * Under v6.8.0.0 a ListField overwrite replaces the value instead of merging it by index.
     *
     * @param array<string, mixed> $originalData
     * @param array<string, mixed> $overwriteData
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('cloneOverwriteListFieldProvider')]
    public function testAListFieldOverwriteReplacesTheList(string $entityName, array $originalData, array $overwriteData, array $expectedData): void
    {
        $this->assertOverwriteResult($entityName, $originalData, $overwriteData, $expectedData);
    }

    /**
     * @deprecated tag:v6.8.0 - removed with the v6.8.0.0 flag; ListField overwrites merge by index before 6.8
     *
     * @param array<string, mixed> $originalData
     * @param array<string, mixed> $overwriteData
     * @param array<string, mixed> $expectedData
     */
    #[DataProvider('cloneOverwriteListFieldLegacyProvider')]
    #[DisabledFeatures(['v6.8.0.0'])]
    public function testAListFieldOverwriteMergesByIndexBefore68(string $entityName, array $originalData, array $overwriteData, array $expectedData): void
    {
        $this->assertOverwriteResult($entityName, $originalData, $overwriteData, $expectedData);
    }

    /**
     * @return iterable<string, array{entityName: string, originalData: array<string, mixed>, overwriteData: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function cloneOverwriteCompatibleProvider(): iterable
    {
        yield 'indexed array replaced by index' => [
            'entityName' => 'clone_array',
            'originalData' => ['indexedArray' => ['a', 'b', 'c']],
            'overwriteData' => ['indexedArray' => ['x']],
            'expectedData' => ['indexedArray' => ['x', 'b', 'c']],
        ];

        yield 'empty array in overwrite keeps the original' => [
            'entityName' => 'clone_array',
            'originalData' => ['emptyArray' => ['a', 'b']],
            'overwriteData' => ['emptyArray' => []],
            'expectedData' => ['emptyArray' => ['a', 'b']],
        ];

        yield 'mixed numeric keys merge by key' => [
            'entityName' => 'clone_array',
            'originalData' => ['mixedArray' => [0 => 'a', 2 => 'b', 5 => 'c']],
            'overwriteData' => ['mixedArray' => [0 => 'x', 2 => 'y']],
            'expectedData' => ['mixedArray' => [0 => 'x', 2 => 'y', 5 => 'c']],
        ];

        yield 'nested indexed arrays merge recursively' => [
            'entityName' => 'clone_array',
            'originalData' => ['nested' => [['a', 'b'], ['c', 'd']]],
            'overwriteData' => ['nested' => [['x']]],
            'expectedData' => ['nested' => [['x', 'b'], ['c', 'd']]],
        ];

        yield 'nested associative arrays merge recursively' => [
            'entityName' => 'clone_array',
            'originalData' => ['nested' => ['level1' => ['level2' => 'original']]],
            'overwriteData' => ['nested' => ['level1' => ['level2' => 'updated', 'new' => 'value']]],
            'expectedData' => ['nested' => ['level1' => ['level2' => 'updated', 'new' => 'value']]],
        ];

        yield 'a new key is added' => [
            'entityName' => 'clone_array',
            'originalData' => ['existingField' => 'value'],
            'overwriteData' => ['newField' => 'newValue'],
            'expectedData' => ['existingField' => 'value', 'newField' => 'newValue'],
        ];

        yield 'a scalar replaces an array' => [
            'entityName' => 'clone_array',
            'originalData' => ['field' => ['old' => 'array']],
            'overwriteData' => ['field' => 'scalar'],
            'expectedData' => ['field' => 'scalar'],
        ];

        yield 'an array replaces a scalar' => [
            'entityName' => 'clone_array',
            'originalData' => ['field' => 'scalar'],
            'overwriteData' => ['field' => ['new' => 'array']],
            'expectedData' => ['field' => ['new' => 'array']],
        ];

        yield 'null replaces a value' => [
            'entityName' => 'clone_array',
            'originalData' => ['field' => 'value'],
            'overwriteData' => ['field' => null],
            'expectedData' => ['field' => null],
        ];

        yield 'nested property mappings merge per field' => [
            'entityName' => 'clone_nested',
            'originalData' => [
                'simpleField' => 'simple',
                'nestedData' => ['nested1' => 'value1', 'nested2' => 'value2'],
                'deeplyNested' => ['level1' => ['level2' => ['level3' => 'deep']]],
            ],
            'overwriteData' => [
                'nestedData' => ['nested1' => 'updated'],
            ],
            'expectedData' => [
                'simpleField' => 'simple',
                'nestedData' => ['nested1' => 'updated', 'nested2' => 'value2'],
                'deeplyNested' => ['level1' => ['level2' => ['level3' => 'deep']]],
            ],
        ];

        yield 'a key without a nested field definition is still merged' => [
            'entityName' => 'clone_nested',
            'originalData' => [
                'nestedData' => ['nested1' => 'value1', 'nested2' => 'value2'],
            ],
            'overwriteData' => [
                'nestedData' => ['undefinedKey' => 'newValue', 'nested1' => 'updated'],
            ],
            'expectedData' => [
                'nestedData' => ['nested1' => 'updated', 'nested2' => 'value2', 'undefinedKey' => 'newValue'],
            ],
        ];
    }

    /**
     * @return iterable<string, array{entityName: string, originalData: array<string, mixed>, overwriteData: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function cloneOverwriteListFieldProvider(): iterable
    {
        yield 'a list field is replaced' => [
            'entityName' => 'clone_list',
            'originalData' => ['listField' => ['value1', 'value2', 'value3']],
            'overwriteData' => ['listField' => ['value2']],
            'expectedData' => ['listField' => ['value2']],
        ];

        yield 'a list field inside a json field is replaced' => [
            'entityName' => 'clone_nested_list',
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
     * @return iterable<string, array{entityName: string, originalData: array<string, mixed>, overwriteData: array<string, mixed>, expectedData: array<string, mixed>}>
     */
    public static function cloneOverwriteListFieldLegacyProvider(): iterable
    {
        yield 'a list field is merged by index' => [
            'entityName' => 'clone_list',
            'originalData' => ['listField' => ['value1', 'value2', 'value3']],
            'overwriteData' => ['listField' => ['value2']],
            'expectedData' => ['listField' => ['value2', 'value2', 'value3']],
        ];

        yield 'a list field inside a json field is merged by index' => [
            'entityName' => 'clone_nested_list',
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
     * @param array<string, mixed> $source
     *
     * @return array<string, mixed>
     */
    private function build(string $entityName, array $source, string $sourceId, string $newId): array
    {
        return $this->builder->build(
            $this->registry->getByEntityName($entityName),
            $source,
            $sourceId,
            $newId,
            [],
            Context::createDefaultContext(),
        );
    }

    /**
     * @param array<string, mixed> $originalData
     * @param array<string, mixed> $overwriteData
     * @param array<string, mixed> $expectedData
     */
    private function assertOverwriteResult(string $entityName, array $originalData, array $overwriteData, array $expectedData): void
    {
        $sourceId = Uuid::randomHex();

        $payload = $this->builder->build(
            $this->registry->getByEntityName($entityName),
            array_merge(['id' => $sourceId], $originalData),
            $sourceId,
            Uuid::randomHex(),
            $overwriteData,
            Context::createDefaultContext(),
        );

        unset($payload['id'], $payload['createdAt'], $payload['updatedAt']);

        static::assertSame($expectedData, $payload);
    }

    /**
     * An order-like source: the root and its delivery both reference one of the root's own addresses.
     *
     * @return array<string, mixed>
     */
    private function orderLikeSource(string $sourceId, string $addressId, string $deliveryId): array
    {
        return [
            'id' => $sourceId,
            'billingAddressId' => $addressId,
            'addresses' => [['id' => $addressId, 'orderId' => $sourceId]],
            'deliveries' => [['id' => $deliveryId, 'orderId' => $sourceId, 'shippingAddressId' => $addressId]],
        ];
    }
}

/**
 * @internal
 */
class ClonePayloadOrderTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_order';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new VersionField(),
            new WasModifiedByUserField(),
            new DateTimeField('created_at', 'createdAt'),
            new DateTimeField('updated_at', 'updatedAt'),
            new FkField('billing_address_id', 'billingAddressId', ClonePayloadAddressTestDefinition::class),
            (new FkField('ext_ref_id', 'extRefId', ClonePayloadAddressTestDefinition::class))->addFlags(new Extension()),
            (new ManyToOneAssociationField('billingAddress', 'billing_address_id', ClonePayloadAddressTestDefinition::class))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('addresses', ClonePayloadAddressTestDefinition::class, 'order_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('deliveries', ClonePayloadDeliveryTestDefinition::class, 'order_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('notes', ClonePayloadNoteTestDefinition::class, 'order_id'))->addFlags(new CascadeDelete(false)),
            (new OneToOneAssociationField('detail', 'id', 'order_id', ClonePayloadDetailTestDefinition::class, false))->addFlags(new CascadeDelete()),
            (new ManyToManyAssociationField('tags', ClonePayloadTagTestDefinition::class, ClonePayloadOrderTagTestDefinition::class, 'clone_order_id', 'clone_tag_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('extDetails', ClonePayloadDetailTestDefinition::class, 'order_id'))->addFlags(new CascadeDelete(), new Extension()),
            (new OneToManyAssociationField('labels', ClonePayloadTranslatedTestDefinition::class, 'order_id'))->addFlags(new CascadeDelete()),
            (new StringField('comment', 'comment'))->addFlags(new CascadeDelete()),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadRequiredUpdatedAtTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_required_updated_at';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new DateTimeField('updated_at', 'updatedAt'))->addFlags(new Required()),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadAddressTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_address';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('order_id', 'orderId', ClonePayloadOrderTestDefinition::class),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadDeliveryTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_delivery';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('order_id', 'orderId', ClonePayloadOrderTestDefinition::class),
            new FkField('shipping_address_id', 'shippingAddressId', ClonePayloadAddressTestDefinition::class),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadDetailTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_detail';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('order_id', 'orderId', ClonePayloadOrderTestDefinition::class),
            new FkField('note_address_id', 'noteAddressId', ClonePayloadAddressTestDefinition::class),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadNoteTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_note';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('order_id', 'orderId', ClonePayloadOrderTestDefinition::class),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadTagTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_tag';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new StringField('name', 'name'),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadOrderTagTestDefinition extends MappingEntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_order_tag';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new FkField('clone_order_id', 'cloneOrderId', ClonePayloadOrderTestDefinition::class))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('clone_tag_id', 'cloneTagId', ClonePayloadTagTestDefinition::class))->addFlags(new PrimaryKey(), new Required()),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadProtectedRootTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_protected_root';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new OneToManyAssociationField('children', ClonePayloadProtectedChildTestDefinition::class, 'parent_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadProtectedChildTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_protected_child';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new CloneProtection(Context::SYSTEM_SCOPE)]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('parent_id', 'parentId', ClonePayloadProtectedRootTestDefinition::class),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadForestTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_forest';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new OneToManyAssociationField('trees', ClonePayloadTreeTestDefinition::class, 'forest_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadTreeTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_tree';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('forest_id', 'forestId', ClonePayloadForestTestDefinition::class),
            new ParentFkField(self::class),
            new ParentAssociationField(self::class),
            new ChildrenAssociationField(self::class),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadTranslatedTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_translated';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('order_id', 'orderId', ClonePayloadOrderTestDefinition::class),
            new TranslatedField('name'),
            new TranslationsAssociationField(ClonePayloadTranslationTestDefinition::class, 'clone_translated_id'),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadTranslationTestDefinition extends EntityTranslationDefinition
{
    public function getEntityName(): string
    {
        return 'clone_translated_translation';
    }

    protected function getParentDefinitionClass(): string
    {
        return ClonePayloadTranslatedTestDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new StringField('name', 'name'),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadResetOnCloneTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_reset_on_clone';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new StringField('name', 'name'),
            (new StringField('reset_value', 'resetValue'))->addFlags(new ResetOnClone()),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadWriteProtectedTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_write_protected';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new StringField('secret', 'secret'))->addFlags(new WriteProtected()),
            (new StringField('token', 'token'))->addFlags(new WriteProtected(Context::SYSTEM_SCOPE)),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadListFieldTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_list';
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
class ClonePayloadArrayTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_array';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new JsonField('indexedArray', 'indexedArray'),
            new JsonField('emptyArray', 'emptyArray'),
            new JsonField('mixedArray', 'mixedArray'),
            new JsonField('nested', 'nested'),
            new StringField('existingField', 'existingField'),
            new StringField('newField', 'newField'),
            new JsonField('field', 'field'),
        ]);
    }
}

/**
 * @internal
 */
class ClonePayloadNestedFieldTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_nested';
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
class ClonePayloadNestedListFieldTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_nested_list';
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

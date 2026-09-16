<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\DataAbstractionLayer;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\DataAbstractionLayerException;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\CloneProtection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityProtection\EntityProtectionCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
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
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
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

        $this->versionManager = new VersionManager(
            $entityWriterMock,
            $entityReaderMock,
            $this->createMock(EntitySearcherInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $serializer,
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(VersionCommitDefinition::class),
            $this->createMock(VersionCommitDataDefinition::class),
            $this->createMock(VersionDefinition::class),
            $this->createMock(LockFactory::class)
        );

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
            ->with(static::equalTo(Context::SYSTEM_SCOPE), static::callback(function (callable $closure) use ($writeContextMockWithVersionId) {
                /** @var callable(MockObject&WriteContext): void $closure */
                $closure($writeContextMockWithVersionId);

                return true;
            }));

        $writeContextMockWithVersionId->expects($this->exactly(2))->method('getContext')->willReturn(Context::createDefaultContext());

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

        $this->versionManager = new VersionManager(
            $this->createMock(EntityWriterInterface::class),
            $entityReaderMock,
            $this->createMock(EntitySearcherInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(VersionCommitDefinition::class),
            $this->createMock(VersionCommitDataDefinition::class),
            $this->createMock(VersionDefinition::class),
            $this->createMock(LockFactory::class)
        );

        $productId = 'product-id';
        static::expectException(DataAbstractionLayerException::class);
        static::expectExceptionMessage(DataAbstractionLayerException::cannotCreateNewVersion('product', $productId)->getMessage());

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

        $this->versionManager = new VersionManager(
            $this->createMock(EntityWriterInterface::class),
            $this->createMock(EntityReaderInterface::class),
            $this->createMock(EntitySearcherInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SerializerInterface::class),
            $registry,
            $this->createMock(VersionCommitDefinition::class),
            $this->createMock(VersionCommitDataDefinition::class),
            $this->createMock(VersionDefinition::class),
            $lockFactory
        );

        $versionId = 'version-id';
        static::expectException(DataAbstractionLayerException::class);
        static::expectExceptionMessage(DataAbstractionLayerException::versionMergeAlreadyLocked($versionId)->getMessage());

        $this->versionManager->merge(
            $versionId,
            $this->createMock(WriteContext::class)
        );
    }

    public function testCloneFailsForCloneProtectedRootEntity(): void
    {
        $entityReader = $this->createMock(EntityReaderInterface::class);
        $entityReader->expects($this->never())->method('read');
        $this->versionManager = new VersionManager(
            $this->createMock(EntityWriterInterface::class),
            $entityReader,
            $this->createMock(EntitySearcherInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(VersionCommitDefinition::class),
            $this->createMock(VersionCommitDataDefinition::class),
            $this->createMock(VersionDefinition::class),
            $this->createMock(LockFactory::class)
        );
        $registry = new StaticDefinitionInstanceRegistry(
            [CloneProtectedVersionManagerTestDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );

        $this->expectExceptionObject(DataAbstractionLayerException::cloneProtected('clone_protected', Context::CRUD_API_SCOPE));

        Context::createDefaultContext()->scope(Context::CRUD_API_SCOPE, function (Context $context) use ($registry): void {
            $this->versionManager->clone(
                $registry->getByEntityName('clone_protected'),
                Uuid::randomHex(),
                Uuid::randomHex(),
                Uuid::randomHex(),
                WriteContext::createFromContext($context),
                new CloneBehavior(),
            );
        });
    }

    public function testCloneSkipsCloneProtectedAssociation(): void
    {
        $entityReader = static::createStub(EntityReaderInterface::class);
        $entityReader->method('read')->willReturn(new EntityCollection([
            (new Entity())->assign(['_uniqueIdentifier' => Uuid::randomHex()]),
        ]));
        $serializer = static::createStub(SerializerInterface::class);
        $serializer->method('serialize')->willReturn(json_encode([
            'id' => Uuid::randomHex(),
            'children' => [['id' => Uuid::randomHex()]],
        ], \JSON_THROW_ON_ERROR));
        $entityWriter = $this->createMock(EntityWriterInterface::class);
        $entityWriter->expects($this->once())->method('insert')->with(
            static::isInstanceOf(VersionManagerRootTestDefinition::class),
            static::callback(static fn (array $data): bool => !isset($data[0]['children'])),
            static::anything(),
        )->willReturn(['clone_root' => []]);
        $this->versionManager = new VersionManager(
            $entityWriter,
            $entityReader,
            $this->createMock(EntitySearcherInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $serializer,
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(VersionCommitDefinition::class),
            $this->createMock(VersionCommitDataDefinition::class),
            $this->createMock(VersionDefinition::class),
            $this->createMock(LockFactory::class)
        );
        $registry = new StaticDefinitionInstanceRegistry(
            [VersionManagerRootTestDefinition::class, CloneProtectedVersionManagerChildTestDefinition::class],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class),
        );
        $context = Context::createDefaultContext();
        $contextWithDisabledAuditLog = Context::createDefaultContext();
        $contextWithDisabledAuditLog->addState(VersionManager::DISABLE_AUDIT_LOG);
        $writeContext = static::createStub(WriteContext::class);
        $writeContextWithVersionId = static::createStub(WriteContext::class);
        $writeContext->method('getContext')->willReturn($context);
        $writeContext->method('createWithVersionId')->willReturn($writeContextWithVersionId);
        $writeContextWithVersionId->method('getContext')->willReturn($contextWithDisabledAuditLog);
        $writeContextWithVersionId->method('scope')->willReturnCallback(static function (string $scope, callable $callback) use ($writeContextWithVersionId): void {
            static::assertSame(Context::SYSTEM_SCOPE, $scope);
            $callback($writeContextWithVersionId);
        });

        $this->versionManager->clone(
            $registry->getByEntityName('clone_root'),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            $writeContext,
            new CloneBehavior(),
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

        $versionManager = new VersionManager(
            $this->createMock(EntityWriterInterface::class),
            $this->createMock(EntityReaderInterface::class),
            $entitySearcherMock,
            $this->createMock(EntityWriteGatewayInterface::class),
            $this->createMock(EventDispatcherInterface::class),
            $this->createMock(SerializerInterface::class),
            $this->createMock(DefinitionInstanceRegistry::class),
            $this->createMock(VersionCommitDefinition::class),
            $this->createMock(VersionCommitDataDefinition::class),
            $this->createMock(VersionDefinition::class),
            $lockFactory
        );

        $versionId = 'non-existent-version-id';

        static::expectException(DataAbstractionLayerException::class);
        static::expectExceptionMessage(DataAbstractionLayerException::versionNotExists($versionId)->getMessage());

        $versionManager->merge($versionId, $this->createMock(WriteContext::class));
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
            new VersionField(),
        ]);
    }
}

/**
 * @internal
 */
class CloneProtectedVersionManagerTestDefinition extends VersionManagerTestDefinition
{
    public function getEntityName(): string
    {
        return 'clone_protected';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new CloneProtection()]);
    }
}

/**
 * @internal
 */
class VersionManagerRootTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_root';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            (new OneToManyAssociationField('children', CloneProtectedVersionManagerChildTestDefinition::class, 'parent_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}

/**
 * @internal
 */
class CloneProtectedVersionManagerChildTestDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'clone_protected_child';
    }

    protected function defineProtections(): EntityProtectionCollection
    {
        return new EntityProtectionCollection([new CloneProtection()]);
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new Required(), new PrimaryKey()),
            new FkField('parent_id', 'parentId', VersionManagerRootTestDefinition::class),
        ]);
    }
}

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
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
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
use Shopware\Core\Framework\DataAbstractionLayer\Write\ClonePayloadBuilder;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriterInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
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
#[Package('framework')]
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

        $writeContextMockWithVersionId->method('getContext')->willReturn(Context::createDefaultContext());

        $registry = new StaticDefinitionInstanceRegistry(
            [
                VersionManagerTestDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $entityWriteResult = $this->versionManager->clone(
            $registry->getByEntityName('product'),
            Uuid::randomHex(),
            Uuid::randomHex(),
            Uuid::randomHex(),
            $writeContextMock,
            static::createStub(CloneBehavior::class)
        );

        static::assertNotCount(0, $entityWriteResult);
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
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $this->versionManager->clone(
            $registry->getByEntityName('product'),
            $productId,
            Uuid::randomHex(),
            Uuid::randomHex(),
            static::createStub(WriteContext::class),
            static::createStub(CloneBehavior::class)
        );
    }

    public function testCloneFailsForCloneProtectedRootEntity(): void
    {
        $entityReader = $this->createMock(EntityReaderInterface::class);
        $entityReader->expects($this->never())->method('read');

        $this->versionManager = $this->createVersionManager(['entityReader' => $entityReader]);
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

    public function testMergeEntityWithLockedVersion(): void
    {
        $lockFactory = $this->createMock(LockFactory::class);

        $registry = new StaticDefinitionInstanceRegistry(
            [],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        $lock = static::createStub(SharedLockInterface::class);
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
            static::createStub(WriteContext::class)
        );
    }

    public function testMergeFailsForNonExistentVersion(): void
    {
        $lockFactory = static::createStub(LockFactory::class);
        $lock = static::createStub(SharedLockInterface::class);
        $lock->method('acquire')->willReturn(true);
        $lockFactory->method('createLock')->willReturn($lock);

        $entitySearcherMock = static::createStub(EntitySearcherInterface::class);

        $entitySearcherMock->method('search')->willReturn(
            new IdSearchResult(0, [], new Criteria(), Context::createDefaultContext())
        );

        $versionManager = $this->createVersionManager([
            'entitySearcher' => $entitySearcherMock,
            'lockFactory' => $lockFactory,
        ]);

        $versionId = 'non-existent-version-id';

        $this->expectExceptionObject(DataAbstractionLayerException::versionNotExists($versionId));

        $versionManager->merge($versionId, static::createStub(WriteContext::class));
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createVersionManager(array $overrides = []): VersionManager
    {
        $defaults = [
            'entityWriter' => static::createStub(EntityWriterInterface::class),
            'entityReader' => static::createStub(EntityReaderInterface::class),
            'entitySearcher' => static::createStub(EntitySearcherInterface::class),
            'entityWriteGateway' => static::createStub(EntityWriteGatewayInterface::class),
            'eventDispatcher' => static::createStub(EventDispatcherInterface::class),
            'serializer' => static::createStub(SerializerInterface::class),
            'registry' => static::createStub(DefinitionInstanceRegistry::class),
            'versionCommitDefinition' => static::createStub(VersionCommitDefinition::class),
            'versionCommitDataDefinition' => static::createStub(VersionCommitDataDefinition::class),
            'versionDefinition' => static::createStub(VersionDefinition::class),
            'lockFactory' => static::createStub(LockFactory::class),
            'clock' => new NativeClock(),
            'clonePayloadBuilder' => new ClonePayloadBuilder(new NativeClock()),
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
            $params['clock'],
            $params['clonePayloadBuilder'],
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

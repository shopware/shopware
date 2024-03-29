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
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Read\EntityReaderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearcherInterface;
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

        $entityReaderMock->expects(static::once())->method('read')->willReturn($entityCollectionMock);
        $serializer->expects(static::once())->method('serialize')
            ->willReturn('{"extensions":{"foreignKeys":{"extensions":[],"apiAlias":null,"manyToOneId":"' . Uuid::randomHex() . '"}}}');

        $writeContextMock = $this->createMock(WriteContext::class);

        $writeContextMockWithVersionId = $this->createMock(WriteContext::class);
        $writeContextMock->expects(static::once())->method('createWithVersionId')->willReturn($writeContextMockWithVersionId);

        $entityWriterMock->expects(static::once())->method('insert')->willReturn([
            'product' => [
                new EntityWriteResult('1', ['languageId' => '1'], 'product', EntityWriteResult::OPERATION_INSERT),
            ],
        ]);

        $writeContextMockWithVersionId->expects(static::once())->method('scope')
            ->with(static::equalTo(Context::SYSTEM_SCOPE), static::callback(function (callable $closure) use ($writeContextMockWithVersionId) {
                /** @var callable(MockObject&WriteContext): void $closure */
                $closure($writeContextMockWithVersionId);

                return true;
            }));

        $writeContextMockWithVersionId->expects(static::exactly(2))->method('getContext')->willReturn(Context::createDefaultContext());

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
        $entityReaderMock->expects(static::once())->method('read')->willReturn(new EntityCollection([]));

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
        $lockFactory->expects(static::once())->method('createLock')->willReturn($lock);

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

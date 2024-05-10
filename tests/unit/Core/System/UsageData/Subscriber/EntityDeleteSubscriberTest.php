<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\UsageData\Subscriber;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\DeadlockException;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityDeleteEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Field;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\WriteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\UsageData\Consent\ConsentService;
use Shopware\Core\System\UsageData\Consent\ConsentState;
use Shopware\Core\System\UsageData\Services\EntityDefinitionService;
use Shopware\Core\System\UsageData\Services\UsageDataAllowListService;
use Shopware\Core\System\UsageData\Subscriber\EntityDeleteSubscriber;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Shopware\Core\Test\Stub\EventDispatcher\CollectingEventDispatcher;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('data-services')]
#[CoversClass(EntityDeleteSubscriber::class)]
class EntityDeleteSubscriberTest extends TestCase
{
    private UsageDataAllowListService $usageDataAllowListServiceMock;

    /**
     * @var array<string, bool>
     */
    private array $requiredParameter = [
        ':entity_name' => false,
        ':entity_ids' => false,
    ];

    protected function setUp(): void
    {
        $usageDataAllowListServiceMock = $this->createMock(UsageDataAllowListService::class);
        $usageDataAllowListServiceMock->method('isEntityAllowed')
            ->willReturn(true);
        $usageDataAllowListServiceMock->method('getFieldsToSelectFromDefinition')
            ->willReturnCallback(function (EntityDefinition $definition) {
                return $definition->getFields();
            });

        $this->usageDataAllowListServiceMock = $usageDataAllowListServiceMock;
    }

    public function testGetSubscribedEvents(): void
    {
        static::assertEquals([
            EntityDeleteEvent::class => 'handleEntityDeleteEvent',
        ], EntityDeleteSubscriber::getSubscribedEvents());
    }

    public function testHandleDeletedEventStoresData(): void
    {
        $productId = Uuid::randomBytes();
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($connection));

        $connection->expects(static::once())
            ->method('commit');

        $connection->expects(static::never())
            ->method('rollBack');

        $statementMock = $this->createMock(Statement::class);
        $statementMock->expects(static::exactly(4))
            ->method('bindValue')
            ->withAnyParameters()
            ->willReturnCallback(function ($key, $value) use ($productId): void {
                if ($key === ':entity_name') {
                    static::assertEquals(EntityWithSinglePrimaryKey::ENTITY_NAME, $value);
                    $this->requiredParameter[':entity_name'] = true;
                }

                if ($key === ':entity_ids') {
                    static::assertEquals(json_encode(['id' => Uuid::fromBytesToHex($productId)]), $value);
                    $this->requiredParameter[':entity_ids'] = true;
                }
            });

        $connection->expects(static::once())
            ->method('prepare')
            ->willReturn($statementMock);

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock('2023-09-01 12:00:00'),
            new ConsentService(
                new StaticSystemConfigService([
                    ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
                ]),
                $this->createMock(EntityRepository::class),
                new CollectingEventDispatcher(),
                new MockClock(),
            ),
        );

        $deleteCommand = new DeleteCommand(
            $definition,
            [
                'id' => $productId,
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'nonStorageAwarePrimaryKey' => Uuid::randomBytes(),
            ],
            $this->createMock(EntityExistence::class)
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$deleteCommand],
            [
                EntityWithSinglePrimaryKey::ENTITY_NAME => ['id' => Uuid::fromBytesToHex($productId)],
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);

        // marks the event as successful --> we write the deletion into the expected table
        $event->success();

        static::assertTrue($this->requiredParameter[':entity_name']);
        static::assertTrue($this->requiredParameter[':entity_ids']);
    }

    public function testHandleDeletedEventStoresDataWillRollbackOnException(): void
    {
        $productId = Uuid::randomBytes();
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($connection));

        $connection->expects(static::once())
            ->method('commit')
            ->willThrowException($this->createMock(DeadlockException::class));

        $connection->expects(static::once())
            ->method('rollBack');

        $statementMock = $this->createMock(Statement::class);
        $statementMock->expects(static::exactly(4))
            ->method('bindValue')
            ->withAnyParameters()
            ->willReturnCallback(function ($key, $value) use ($productId): void {
                if ($key === ':entity_name') {
                    static::assertEquals(EntityWithSinglePrimaryKey::ENTITY_NAME, $value);
                    $this->requiredParameter[':entity_name'] = true;
                }

                if ($key === ':entity_ids') {
                    static::assertEquals(json_encode(['id' => Uuid::fromBytesToHex($productId)]), $value);
                    $this->requiredParameter[':entity_ids'] = true;
                }
            });

        $connection->expects(static::once())
            ->method('prepare')
            ->willReturn($statementMock);

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock('2023-09-01 12:00:00'),
            new ConsentService(
                new StaticSystemConfigService([
                    ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
                ]),
                $this->createMock(EntityRepository::class),
                new CollectingEventDispatcher(),
                new MockClock(),
            ),
        );

        $deleteCommand = new DeleteCommand(
            $definition,
            [
                'id' => $productId,
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'nonStorageAwarePrimaryKey' => Uuid::randomBytes(),
            ],
            $this->createMock(EntityExistence::class)
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [$deleteCommand],
            [
                EntityWithSinglePrimaryKey::ENTITY_NAME => ['id' => Uuid::fromBytesToHex($productId)],
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);

        // marks the event as successful --> we write the deletion into the expected table
        $event->success();

        static::assertTrue($this->requiredParameter[':entity_name']);
        static::assertTrue($this->requiredParameter[':entity_ids']);
    }

    public function testHandleDeletedEventStoresDataMultipleEntities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::once())
            ->method('createQueryBuilder')
            ->willReturn(new QueryBuilder($connection));

        $connection->expects(static::once())
            ->method('commit');

        $connection->expects(static::never())
            ->method('rollBack');

        $statementMock = $this->createMock(Statement::class);
        // assert bindValue to be called 2 * 4 times (2 entities with 5 parameters)
        $statementMock->expects(static::exactly(8))
            ->method('bindValue')
            ->withAnyParameters();

        $connection->expects(static::once())
            ->method('prepare')
            ->willReturn($statementMock);

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock('2023-09-01 12:00:00'),
            new ConsentService(
                new StaticSystemConfigService([
                    ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
                ]),
                $this->createMock(EntityRepository::class),
                new CollectingEventDispatcher(),
                new MockClock(),
            ),
        );

        $deleteCommand = new DeleteCommand(
            $definition,
            [
                'id' => Uuid::randomBytes(),
                'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                'nonStorageAwarePrimaryKey' => Uuid::randomBytes(),
            ],
            $this->createMock(EntityExistence::class)
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [
                $deleteCommand,
                $deleteCommand,
            ],
            [
                EntityWithSinglePrimaryKey::ENTITY_NAME => [
                    'first-id' => 'product-id-1',
                    'second-id' => 'product-id-1',
                ],
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);

        // marks the event as successful --> we write the deletion into the expected table
        $event->success();
    }

    public function testHandleDeletedEventReturnsEarlyOnEmptyEvent(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())
            ->method('beginTransaction');

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock('2023-09-01 12:00:00'),
            new ConsentService(
                new StaticSystemConfigService([
                    ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
                ]),
                $this->createMock(EntityRepository::class),
                new CollectingEventDispatcher(),
                new MockClock(),
            ),
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [],
            [
                EntityWithSinglePrimaryKey::ENTITY_NAME => [], // no ids given
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);
        $event->success();
    }

    public function testHandleDeletedEventIgnoresEntities(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())
            ->method('createQueryBuilder');

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock(),
            new ConsentService(
                new StaticSystemConfigService([
                    ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::ACCEPTED->value,
                ]),
                $this->createMock(EntityRepository::class),
                new CollectingEventDispatcher(),
                new MockClock(),
            ),
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [],
            [
                IgnoredEntityDefinition::ENTITY_NAME => ['id' => '123'], // this entity is not registered
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);
        $event->success();
    }

    public function testIfDeletionsAreNotStoredWhenConsentIsNotGiven(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(static::never())
            ->method('beginTransaction');

        $consentService = new ConsentService(
            new StaticSystemConfigService([
                ConsentService::SYSTEM_CONFIG_KEY_CONSENT_STATE => ConsentState::REQUESTED->value,
            ]),
            $this->createMock(EntityRepository::class),
            new CollectingEventDispatcher(),
            new MockClock(),
        );

        $registry = new StaticDefinitionInstanceRegistry(
            [new EntityWithSinglePrimaryKey()],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );
        $definition = new EntityWithSinglePrimaryKey();
        $definition->compile($registry);

        $subscriber = new EntityDeleteSubscriber(
            new EntityDefinitionService([$definition], $this->usageDataAllowListServiceMock),
            $connection,
            new MockClock('2023-09-01 12:00:00'),
            $consentService,
        );

        $event = DeletedEvent::create(
            WriteContext::createFromContext(Context::createDefaultContext()),
            [],
            [
                EntityWithSinglePrimaryKey::ENTITY_NAME => ['id' => '123'],
            ]
        );

        $subscriber->handleEntityDeleteEvent($event);
        $event->success();

        static::assertFalse($consentService->isConsentAccepted());
    }
}

/**
 * @internal
 */
class EntityWithSinglePrimaryKey extends EntityDefinition
{
    public const ENTITY_NAME = 'entity_with_single_primary_key';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            (new NonStorageAwareField('nonStorageAwarePrimaryKey'))->addFlags(new PrimaryKey()),
            new NonStorageAwareField('nonStorageAware'),
            new ReferenceVersionField(ProductDefinition::class, 'product_version_id'),
        ]);
    }
}

/**
 * @internal
 */
class IgnoredEntityDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'ignored_entity_definition';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey()),
            new NonStorageAwareField('name'),
            new ReferenceVersionField(ProductDefinition::class, 'product_version_id'),
        ]);
    }
}

/**
 * @internal
 */
class NonStorageAwareField extends Field
{
    protected function getSerializerClass(): string
    {
        /** @phpstan-ignore-next-line Should be a class-string but we will never use this value */
        return '';
    }
}

/**
 * @internal
 */
class DeletedEvent extends EntityDeleteEvent
{
    /**
     * @var array<array<string, string>>
     */
    private static array $ids = [];

    /**
     * @param array<WriteCommand> $commands
     * @param array<array<string, string>> $ids
     */
    public static function create(WriteContext $writeContext, array $commands, array $ids = []): EntityDeleteEvent
    {
        self::$ids = $ids;

        return parent::create($writeContext, $commands);
    }

    /**
     * @return array<array<string, string>|string>
     */
    public function getIds(string $entity): array
    {
        return \array_key_exists($entity, self::$ids) ? self::$ids[$entity] : [];
    }
}

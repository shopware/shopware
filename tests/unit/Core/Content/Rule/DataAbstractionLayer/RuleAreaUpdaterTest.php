<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleAreaUpdater;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityWriteResult;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriteGatewayInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticDefinitionInstanceRegistry;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
#[CoversClass(RuleAreaUpdater::class)]
class RuleAreaUpdaterTest extends TestCase
{
    private Connection&Stub $connection;

    private RuleDefinition $definition;

    private Stub&RuleConditionRegistry $conditionRegistry;

    private RuleAreaUpdater $areaUpdater;

    private StaticDefinitionInstanceRegistry $registry;

    private MockClock $clock;

    protected function setUp(): void
    {
        $this->connection = static::createStub(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());

        $this->conditionRegistry = static::createStub(RuleConditionRegistry::class);

        $registry = new StaticDefinitionInstanceRegistry(
            [
                RuleAreaDefinitionTest::class,
                RuleAreaTestManyToMany::class,
                RuleAreaTestOneToMany::class,
                RuleAreaTestOneToOne::class,
                RuleAreaTestManyToOne::class,
                ReferenceDefinition::class,
            ],
            static::createStub(ValidatorInterface::class),
            static::createStub(EntityWriteGatewayInterface::class)
        );

        /** @var RuleDefinition $entityDefinition */
        $entityDefinition = $registry->getByEntityName('rule');
        $this->definition = $entityDefinition;
        $this->registry = $registry;

        $this->clock = new MockClock('2026-01-13 11:00:00');
        $this->areaUpdater = $this->createAreaUpdater();
    }

    public function testUpdate(): void
    {
        $id = Uuid::randomHex();

        $resultStatement = static::createStub(Result::class);
        $resultStatement->method('fetchAllAssociative')->willReturn([
            [
                'array_key' => $id,
                'oneToOne' => '1',
                'oneToMany' => '1',
                'manyToOne' => '1',
                'manyToMany' => '1',
            ],
        ]);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects($this->once())
            ->method('executeQuery')
            ->willReturnCallback(function (string $sql, array $params) use ($resultStatement, $id): Result {
                static::assertSame(['ids' => Uuid::fromHexToBytesList([$id]), 'flowTypes' => ['orderTags']], $params);

                return $resultStatement;
            });

        $statement = $this->createMock(Statement::class);
        $params = [
            ['areas', json_encode([RuleAreas::PRODUCT_AREA, RuleAreas::PROMOTION_AREA, RuleAreas::PAYMENT_AREA, RuleAreas::SHIPPING_AREA])],
            ['id', Uuid::fromHexToBytes($id)],
            ['updatedAt', $this->clock->now()->format(Defaults::STORAGE_DATE_TIME_FORMAT)],
        ];
        $matcher = $this->exactly(\count($params));
        $statement->expects($matcher)
            ->method('bindValue')
            ->willReturnCallback(static function (string $key, $value) use ($matcher, $params): void {
                self::assertSame($params[$matcher->numberOfInvocations() - 1][0], $key);
                self::assertSame($params[$matcher->numberOfInvocations() - 1][1], $value);
            });
        $statement->expects($this->once())->method('executeStatement')->willReturn(1);
        $connection->method('prepare')->willReturn($statement);

        $this->conditionRegistry->method('getFlowRuleNames')->willReturn(['orderTags']);

        $this->createAreaUpdater($connection)->update([$id]);
    }

    public function testTriggerChangeset(): void
    {
        $fieldCollection = $this->definition->getFields();

        $oneToManyField = $fieldCollection->get('oneToMany');
        $manyToOneField = $fieldCollection->get('manyToOne');
        $manyToManyField = $fieldCollection->get('manyToMany');

        static::assertInstanceOf(OneToManyAssociationField::class, $oneToManyField);
        static::assertInstanceOf(ManyToOneAssociationField::class, $manyToOneField);
        static::assertInstanceOf(ManyToManyAssociationField::class, $manyToManyField);

        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [
            new DeleteCommand($oneToManyField->getReferenceDefinition(), [], static::createStub(EntityExistence::class)),
            new UpdateCommand($manyToOneField->getReferenceDefinition(), [], [], static::createStub(EntityExistence::class), ''),
            new UpdateCommand($oneToManyField->getReferenceDefinition(), ['rule_id' => 'foo'], [], static::createStub(EntityExistence::class), ''),
            new UpdateCommand($manyToManyField->getReferenceDefinition(), ['rule_id' => 'foo'], [], static::createStub(EntityExistence::class), ''),
        ]);

        $this->areaUpdater->triggerChangeSet($event);

        /** @var DeleteCommand[]|UpdateCommand[] $commands */
        $commands = $event->getCommands();

        static::assertCount(4, $commands);
        static::assertTrue($commands[0]->requiresChangeSet());
        static::assertFalse($commands[1]->requiresChangeSet());
        static::assertTrue($commands[2]->requiresChangeSet());
        static::assertTrue($commands[3]->requiresChangeSet());
    }

    public function testOnEntityWritten(): void
    {
        $context = Context::createDefaultContext();

        $idA = Uuid::randomHex();
        $idB = Uuid::randomBytes();
        $idC = Uuid::randomBytes();
        $idD = Uuid::randomBytes();
        $idE = Uuid::randomBytes();

        $event = new EntityWrittenContainerEvent($context, new NestedEventCollection([
            new EntityWrittenEvent('many_to_one', [
                new EntityWriteResult($idA, [], 'many_to_one', EntityWriteResult::OPERATION_INSERT),
            ], $context, []),
            new EntityWrittenEvent('one_to_many', [
                new EntityWriteResult($idA, ['ruleId' => $idA], 'one_to_many', EntityWriteResult::OPERATION_INSERT),
                new EntityWriteResult($idA, [], 'one_to_many', EntityWriteResult::OPERATION_UPDATE, null, new ChangeSet(
                    ['rule_id' => $idB],
                    ['rule_id' => $idC],
                    false
                )),
                new EntityWriteResult($idA, [], 'one_to_many', EntityWriteResult::OPERATION_DELETE, null, new ChangeSet(
                    ['rule_id' => $idD],
                    ['rule_id' => null],
                    true
                )),
            ], $context, []),
            new EntityWrittenEvent('mapping', [
                new EntityWriteResult(
                    $idA,
                    [
                        'ruleId' => Uuid::fromBytesToHex($idE),
                        'referenceId' => Uuid::randomHex(),
                    ],
                    'mapping',
                    EntityWriteResult::OPERATION_INSERT
                ),
            ], $context, []),
        ]), []);

        $resultStatement = $this->createMock(Result::class);
        $resultStatement->expects($this->once())->method('fetchAllAssociative')->willReturn([]);

        $connection = $this->createMock(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn(new MySQLPlatform());
        $connection->expects($this->once())
            ->method('executeQuery')
            ->willReturnCallback(function (string $sql, array $params) use ($resultStatement, $idA, $idB, $idC, $idD, $idE): Result {
                static::assertSame(['ids' => [Uuid::fromHexToBytes($idA), $idB, $idC, $idD, $idE], 'flowTypes' => ['orderTags']], $params);

                return $resultStatement;
            });

        $statement = static::createStub(Statement::class);
        $statement->method('getWrappedStatement')->willReturn(static::createStub(\Doctrine\DBAL\Driver\Statement::class));
        $connection->method('prepare')->willReturn($statement);

        $this->conditionRegistry->method('getFlowRuleNames')->willReturn(['orderTags']);

        $this->createAreaUpdater($connection)->onEntityWritten($event);
    }

    private function createAreaUpdater(?Connection $connection = null): RuleAreaUpdater
    {
        return new RuleAreaUpdater(
            $connection ?? $this->connection,
            $this->definition,
            $this->conditionRegistry,
            static::createStub(CacheInvalidator::class),
            $this->registry,
            $this->clock,
        );
    }
}

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class RuleAreaDefinitionTest extends RuleDefinition
{
    public function getEntityName(): string
    {
        return 'rule';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new OneToOneAssociationField('oneToOne', 'one_to_one', 'id', RuleAreaTestOneToOne::class))->addFlags(new RuleAreas(RuleAreas::PRODUCT_AREA)),
            (new OneToManyAssociationField('oneToMany', RuleAreaTestOneToMany::class, 'rule_id'))->addFlags(new RuleAreas(RuleAreas::PROMOTION_AREA)),
            (new ManyToOneAssociationField('manyToOne', 'many_to_one', RuleAreaTestManyToOne::class))->addFlags(new RuleAreas(RuleAreas::PAYMENT_AREA)),
            (new ManyToManyAssociationField('manyToMany', RuleAreaDefinitionTest::class, RuleAreaTestManyToMany::class, 'rule_id', 'reference_id'))->addFlags(new RuleAreas(RuleAreas::SHIPPING_AREA)),
            new FkField('rule_id', 'ruleId', RuleAreaDefinitionTest::class),
        ]);
    }
}

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class RuleAreaTestOneToOne extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'one_to_one';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
        ]);
    }
}

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class RuleAreaTestOneToMany extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'one_to_many';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
            new FkField('rule_id', 'ruleId', RuleAreaDefinitionTest::class),
        ]);
    }
}

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class RuleAreaTestManyToOne extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'many_to_one';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new IdField('id', 'id'),
        ]);
    }
}

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class RuleAreaTestManyToMany extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'mapping';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new FkField('rule_id', 'ruleId', RuleAreaDefinitionTest::class),
            new FkField('reference_id', 'referenceId', ReferenceDefinition::class),
        ]);
    }
}

/**
 * @internal
 */
#[Package('fundamentals@after-sales')]
class ReferenceDefinition extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'reference';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new ManyToManyAssociationField('rule', RuleAreaDefinitionTest::class, RuleAreaTestManyToMany::class, 'reference_id', 'rule_id'),
        ]);
    }
}

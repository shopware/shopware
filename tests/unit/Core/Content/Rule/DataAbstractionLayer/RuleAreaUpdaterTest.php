<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleAreaUpdater;
use Shopware\Core\Content\Rule\RuleDefinition;
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
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @internal
 */
#[Package('services-settings')]
#[CoversClass(RuleAreaUpdater::class)]
class RuleAreaUpdaterTest extends TestCase
{
    private Connection&MockObject $connection;

    private RuleDefinition $definition;

    private MockObject&RuleConditionRegistry $conditionRegistry;

    private RuleAreaUpdater $areaUpdater;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->connection->method('getDatabasePlatform')->willReturn(new MySQL80Platform());

        $this->conditionRegistry = $this->createMock(RuleConditionRegistry::class);

        $registry = new StaticDefinitionInstanceRegistry(
            [
                RuleAreaDefinitionTest::class,
                RuleAreaTestManyToMany::class,
                RuleAreaTestOneToMany::class,
                RuleAreaTestOneToOne::class,
                RuleAreaTestManyToOne::class,
            ],
            $this->createMock(ValidatorInterface::class),
            $this->createMock(EntityWriteGatewayInterface::class)
        );

        /** @var RuleDefinition $entityDefinition */
        $entityDefinition = $registry->getByEntityName('rule');
        $this->definition = $entityDefinition;

        $cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $this->areaUpdater = new RuleAreaUpdater(
            $this->connection,
            $this->definition,
            $this->conditionRegistry,
            $cacheInvalidator,
            $registry
        );
    }

    public function testUpdate(): void
    {
        $id = Uuid::randomHex();

        $resultStatement = $this->createMock(Result::class);
        $resultStatement->method('fetchAllAssociative')->willReturn([
            [
                'array_key' => $id,
                'oneToOne' => '1',
                'oneToMany' => '1',
                'manyToOne' => '1',
                'manyToMany' => '1',
            ],
        ]);

        $this->connection->method('executeQuery')->with(
            'SELECT LOWER(HEX(`rule`.`id`)) AS array_key, IF(`rule`.`one_to_one` IS NOT NULL, 1, 0) AS oneToOne, '
            . 'EXISTS(SELECT 1 FROM `one_to_many` WHERE `rule_id` = `rule`.`id`) AS oneToMany, IF(`rule`.`many_to_one` IS NOT NULL, 1, 0) AS manyToOne, '
            . 'EXISTS(SELECT 1 FROM `mapping` WHERE `rule_id` = `rule`.`id`) AS manyToMany, '
            . 'EXISTS(SELECT 1 FROM rule_condition WHERE (`rule_id` = `rule`.`id`) AND (`type` IN (:flowTypes))) AS flowCondition '
            . 'FROM rule WHERE `rule`.`id` IN (:ids)',
            ['ids' => Uuid::fromHexToBytesList([$id]), 'flowTypes' => ['orderTags']],
            ['ids' => ArrayParameterType::BINARY, 'flowTypes' => ArrayParameterType::STRING]
        )->willReturn($resultStatement);

        $statement = $this->createMock(Statement::class);
        $statement->expects(static::once())->method('executeStatement')->with([
            'areas' => json_encode([RuleAreas::PRODUCT_AREA, RuleAreas::PROMOTION_AREA, RuleAreas::PAYMENT_AREA, RuleAreas::SHIPPING_AREA]),
            'id' => Uuid::fromHexToBytes($id),
        ]);
        $this->connection->method('prepare')->willReturn($statement);

        $this->conditionRegistry->method('getFlowRuleNames')->willReturn(['orderTags']);

        $this->areaUpdater->update([$id]);
    }

    public function testTriggerChangeset(): void
    {
        $fieldCollection = $this->definition->getFields();

        $oneToManyField = $fieldCollection->get('oneToMany');
        $manyToOneField = $fieldCollection->get('manyToOne');

        static::assertInstanceOf(OneToManyAssociationField::class, $oneToManyField);
        static::assertInstanceOf(ManyToOneAssociationField::class, $manyToOneField);

        $event = new PreWriteValidationEvent(WriteContext::createFromContext(Context::createDefaultContext()), [
            new DeleteCommand($oneToManyField->getReferenceDefinition(), [], $this->createMock(EntityExistence::class)),
            new UpdateCommand($manyToOneField->getReferenceDefinition(), [], [], $this->createMock(EntityExistence::class), ''),
            new UpdateCommand($oneToManyField->getReferenceDefinition(), ['rule_id' => 'foo'], [], $this->createMock(EntityExistence::class), ''),
        ]);

        $this->areaUpdater->triggerChangeSet($event);

        /** @var DeleteCommand[]|UpdateCommand[] $commands */
        $commands = $event->getCommands();

        static::assertCount(3, $commands);
        static::assertTrue($commands[0]->requiresChangeSet());
        static::assertFalse($commands[1]->requiresChangeSet());
        static::assertTrue($commands[2]->requiresChangeSet());
    }

    public function testOnEntityWritten(): void
    {
        $context = Context::createDefaultContext();

        $idA = Uuid::randomHex();
        $idB = Uuid::randomBytes();
        $idC = Uuid::randomBytes();
        $idD = Uuid::randomBytes();

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
        ]), []);

        $resultStatement = $this->createMock(Result::class);
        $resultStatement->expects(static::once())->method('fetchAllAssociative')->willReturn([]);
        $this->connection->method('executeQuery')
            ->with(static::anything(), static::equalTo(['ids' => [Uuid::fromHexToBytes($idA), $idB, $idC, $idD], 'flowTypes' => ['orderTags']]))
            ->willReturn($resultStatement);

        $statement = $this->createMock(Statement::class);
        $statement->method('getWrappedStatement')->willReturn($this->createMock(\Doctrine\DBAL\Driver\Statement::class));
        $this->connection->method('prepare')->willReturn($statement);

        $this->conditionRegistry->method('getFlowRuleNames')->willReturn(['orderTags']);

        $this->areaUpdater->onEntityWritten($event);
    }
}

/**
 * @internal
 */
#[Package('services-settings')]
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
#[Package('services-settings')]
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
#[Package('services-settings')]
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
#[Package('services-settings')]
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
#[Package('services-settings')]
class RuleAreaTestManyToMany extends EntityDefinition
{
    public function getEntityName(): string
    {
        return 'mapping';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new FkField('rule_id', 'ruleId', RuleDefinition::class),
            new FkField('reference_id', 'referenceId', 'ReferenceMock'),
        ]);
    }
}

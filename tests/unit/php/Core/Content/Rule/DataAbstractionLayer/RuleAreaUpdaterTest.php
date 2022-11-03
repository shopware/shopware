<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Rule\DataAbstractionLayer;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Result;
use Doctrine\DBAL\Statement;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Rule\DataAbstractionLayer\RuleAreaUpdater;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\CompiledFieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\DefinitionInstanceRegistry;
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
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\ChangeSet;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\DeleteCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Command\UpdateCommand;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Validation\PreWriteValidationEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Write\WriteContext;
use Shopware\Core\Framework\Event\NestedEventCollection;
use Shopware\Core\Framework\Rule\Collector\RuleConditionRegistry;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 * @covers \Shopware\Core\Content\Rule\DataAbstractionLayer\RuleAreaUpdater
 */
class RuleAreaUpdaterTest extends TestCase
{
    /**
     * @var MockObject|Connection
     */
    private $connection;

    /**
     * @var MockObject|RuleDefinition
     */
    private $definition;

    /**
     * @var MockObject|RuleConditionRegistry
     */
    private $conditionRegistry;

    /**
     * @var MockObject|CacheInvalidator
     */
    private $cacheInvalidator;

    private RuleAreaUpdater $areaUpdater;

    public function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->definition = $this->createMock(RuleDefinition::class);
        $this->conditionRegistry = $this->createMock(RuleConditionRegistry::class);
        $this->cacheInvalidator = $this->createMock(CacheInvalidator::class);
        $this->areaUpdater = new RuleAreaUpdater(
            $this->connection,
            $this->definition,
            $this->conditionRegistry,
            $this->cacheInvalidator
        );
    }

    public function testUpdate(): void
    {
        $id = Uuid::randomHex();

        $this->definition->method('getFields')->willReturn($this->getFieldCollection());

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
            ['ids' => Connection::PARAM_STR_ARRAY, 'flowTypes' => Connection::PARAM_STR_ARRAY]
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
        $fieldCollection = $this->getFieldCollection();
        $this->definition->method('getFields')->willReturn($fieldCollection);

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
        $fieldCollection = $this->getFieldCollection();
        $this->definition->method('getFields')->willReturn($fieldCollection);
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

    private function getFieldCollection(): CompiledFieldCollection
    {
        $registry = $this->createMock(DefinitionInstanceRegistry::class);

        $oneToOneField = (new OneToOneAssociationField('oneToOne', 'one_to_one', 'id', 'OneToOneMock'))->addFlags(new RuleAreas(RuleAreas::PRODUCT_AREA));
        $oneToOneField->assign(['registry' => $registry]);

        $oneToManyField = (new OneToManyAssociationField('oneToMany', 'OneToManyMock', 'rule_id'))->addFlags(new RuleAreas(RuleAreas::PROMOTION_AREA));
        $oneToManyField->assign(['registry' => $registry]);

        $manyToOneField = (new ManyToOneAssociationField('manyToOne', 'many_to_one', 'ManyToOneMock'))->addFlags(new RuleAreas(RuleAreas::PAYMENT_AREA));
        $manyToOneField->assign(['registry' => $registry]);

        $manyToManyField = (new ManyToManyAssociationField('manyToMany', 'ManyToManyMock', 'MappingMock', 'rule_id', 'reference_id'))->addFlags(new RuleAreas(RuleAreas::SHIPPING_AREA));
        $manyToManyField->assign(['registry' => $registry]);

        $fkField = new FkField('rule_id', 'ruleId', RuleDefinition::class);
        $fkField->assign(['registry' => $registry]);

        $oneToOneDefinition = $this->createMock(EntityDefinition::class);
        $oneToOneDefinition->method('getClass')->willReturn('OneToOneMock');
        $oneToOneDefinition->method('getEntityName')->willReturn('one_to_one');
        $oneToOneDefinition->method('getFields')->willReturn(new CompiledFieldCollection($registry, [
            new IdField('id', 'id'),
        ]));

        $oneToManyDefinition = $this->createMock(EntityDefinition::class);
        $oneToManyDefinition->method('getClass')->willReturn('OneToManyMock');
        $oneToManyDefinition->method('getEntityName')->willReturn('one_to_many');
        $oneToManyDefinition->method('getFields')->willReturn(new CompiledFieldCollection($registry, [
            new IdField('id', 'id'),
            $fkField,
        ]));

        $manyToOneDefinition = $this->createMock(EntityDefinition::class);
        $manyToOneDefinition->method('getClass')->willReturn('ManyToOneMock');
        $manyToOneDefinition->method('getEntityName')->willReturn('many_to_one');
        $manyToOneDefinition->method('getFields')->willReturn(new CompiledFieldCollection($registry, [
            new IdField('id', 'id'),
        ]));

        $mappingDefinition = $this->createMock(EntityDefinition::class);
        $mappingDefinition->method('getClass')->willReturn('MappingMock');
        $mappingDefinition->method('getEntityName')->willReturn('mapping');
        $mappingDefinition->method('getFields')->willReturn(new CompiledFieldCollection($registry, [
            new FkField('rule_id', 'ruleId', RuleDefinition::class),
            new FkField('reference_id', 'referenceId', 'ReferenceMock'),
        ]));

        $ruleDefinition = $this->createMock(EntityDefinition::class);
        $mappingDefinition->method('getClass')->willReturn(RuleDefinition::class);
        $mappingDefinition->method('getEntityName')->willReturn(RuleDefinition::ENTITY_NAME);

        $registry->method('getByClassOrEntityName')->willReturnCallback(function (string $class) use ($oneToOneDefinition, $oneToManyDefinition, $manyToOneDefinition, $mappingDefinition, $ruleDefinition) {
            switch ($class) {
                case 'OneToOneMock':
                    return $oneToOneDefinition;
                case 'OneToManyMock':
                    return $oneToManyDefinition;
                case 'ManyToOneMock':
                    return $manyToOneDefinition;
                case 'MappingMock':
                    return $mappingDefinition;
                default:
                    return $ruleDefinition;
            }
        });

        return new CompiledFieldCollection($registry, [$oneToOneField, $oneToManyField, $manyToOneField, $manyToManyField]);
    }
}

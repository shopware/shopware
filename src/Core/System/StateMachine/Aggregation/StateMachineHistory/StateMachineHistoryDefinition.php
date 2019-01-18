<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\User\UserDefinition;

class StateMachineHistoryDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'state_machine_history';
    }

    public static function getEntityClass(): string
    {
        return StateMachineHistoryEntity::class;
    }

    public static function getCollectionClass(): string
    {
        return StateMachineHistoryCollection::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('state_machine_id', 'stateMachineId', StateMachineDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('stateMachine', 'state_machine_id', StateMachineDefinition::class, true),

            (new StringField('entity_class_name', 'entityName'))->setFlags(new Required()),
            (new JsonField('entity_id', 'entityId'))->setFlags(new Required()),

            (new FkField('from_state_id', 'fromStateId', StateMachineStateDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('fromStateMachineState', 'from_state_id', StateMachineStateDefinition::class, true),

            (new FkField('to_state_id', 'toStateId', StateMachineStateDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('toStateMachineState', 'to_state_id', StateMachineStateDefinition::class, true),

            new StringField('action_name', 'transitionActionName'),

            new FkField('user_id', 'userId', UserDefinition::class),
            new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, false),

            new CreatedAtField(),
        ]);
    }
}

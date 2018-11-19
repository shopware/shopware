<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\StateMachineDefinition;

class StateMachineTransitionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'state_machine_transition';
    }

    public static function getStructClass(): string
    {
        return StateMachineTransitionStruct::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new StringField('action_name', 'actionName'))->setFlags(new Required()),

            (new FkField('state_machine_id', 'stateMachineId', StateMachineDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('stateMachine', 'state_machine_id', StateMachineDefinition::class, false),

            (new FkField('from_state_id', 'fromStateId', StateMachineStateDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('fromState', 'from_state_id', StateMachineStateDefinition::class, true),

            (new FkField('to_state_id', 'toStateId', StateMachineStateDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('toState', 'to_state_id', StateMachineStateDefinition::class, true),

            new CreatedAtField(),
            new UpdatedAtField(),
        ]);
    }
}

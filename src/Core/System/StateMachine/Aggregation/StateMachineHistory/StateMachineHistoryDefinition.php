<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory;

use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\User\UserDefinition;

class StateMachineHistoryDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'state_machine_history';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StateMachineHistoryEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StateMachineHistoryCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),

            (new FkField('state_machine_id', 'stateMachineId', StateMachineDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('stateMachine', 'state_machine_id', StateMachineDefinition::class, 'id', false),

            (new StringField('entity_name', 'entityName'))->setFlags(new Required()),
            (new JsonField('entity_id', 'entityId'))->setFlags(new Required()),

            (new FkField('from_state_id', 'fromStateId', StateMachineStateDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('fromStateMachineState', 'from_state_id', StateMachineStateDefinition::class, 'id', false),

            (new FkField('to_state_id', 'toStateId', StateMachineStateDefinition::class))->setFlags(new Required()),
            new ManyToOneAssociationField('toStateMachineState', 'to_state_id', StateMachineStateDefinition::class, 'id', false),

            new StringField('action_name', 'transitionActionName'),

            new FkField('user_id', 'userId', UserDefinition::class),

            (new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false))
                ->addFlags(new ReadProtected(SalesChannelApiSource::class)),
        ]);
    }
}

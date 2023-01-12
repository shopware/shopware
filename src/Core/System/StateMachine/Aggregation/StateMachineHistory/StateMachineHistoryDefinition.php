<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory;

use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deprecated;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;
use Shopware\Core\System\StateMachine\StateMachineDefinition;
use Shopware\Core\System\User\UserDefinition;

#[Package('checkout')]
class StateMachineHistoryDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'state_machine_history';

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
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('state_machine_id', 'stateMachineId', StateMachineDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('stateMachine', 'state_machine_id', StateMachineDefinition::class, 'id', false),

            (new StringField('entity_name', 'entityName'))->addFlags(new Required()),

            (new FkField('from_state_id', 'fromStateId', StateMachineStateDefinition::class))->addFlags(new Required()),
            (new ManyToOneAssociationField('fromStateMachineState', 'from_state_id', StateMachineStateDefinition::class, 'id', false))->addFlags(new ApiAware()),

            (new FkField('to_state_id', 'toStateId', StateMachineStateDefinition::class))->addFlags(new Required()),
            (new ManyToOneAssociationField('toStateMachineState', 'to_state_id', StateMachineStateDefinition::class, 'id', false))->addFlags(new ApiAware()),
            new StringField('action_name', 'transitionActionName'),
            new FkField('user_id', 'userId', UserDefinition::class),

            (new ManyToOneAssociationField('user', 'user_id', UserDefinition::class, 'id', false)),
        ]);

        if (Feature::isActive('v6.6.0.0')) {
            $collection->add(
                (new IdField('referenced_id', 'referencedId'))->addFlags(new Required())
            );
            $collection->add(
                (new IdField('referenced_version_id', 'referencedVersionId'))->addFlags(new Required())
            );
        } else {
            $collection->add(
                (new JsonField('entity_id', 'entityId'))->addFlags(new Required(), new Deprecated('v6.5.0', 'v6.6.0', 'Use the dedicated properties \'referencedId\' and \'referencedVersionId\''))
            );
            $collection->add(new IdField('referenced_id', 'referencedId'));
            $collection->add(new IdField('referenced_version_id', 'referencedVersionId'));
        }

        return $collection;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\System\StateMachine\Aggregation\StateMachineState;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund\OrderTransactionCaptureRefundDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineHistory\StateMachineHistoryDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineTransition\StateMachineTransitionDefinition;
use Shopware\Core\System\StateMachine\StateMachineDefinition;

#[Package('checkout')]
class StateMachineStateDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'state_machine_state';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return StateMachineStateEntity::class;
    }

    public function getCollectionClass(): string
    {
        return StateMachineStateCollection::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new StringField('technical_name', 'technicalName'))->addFlags(new ApiAware(), new Required(), new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('name'))->addFlags(new ApiAware(), new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),

            (new FkField('state_machine_id', 'stateMachineId', StateMachineDefinition::class))->addFlags(new Required()),
            new ManyToOneAssociationField('stateMachine', 'state_machine_id', StateMachineDefinition::class, 'id', false),
            new OneToManyAssociationField('fromStateMachineTransitions', StateMachineTransitionDefinition::class, 'from_state_id'),
            new OneToManyAssociationField('toStateMachineTransitions', StateMachineTransitionDefinition::class, 'to_state_id'),

            (new TranslationsAssociationField(StateMachineStateTranslationDefinition::class, 'state_machine_state_id'))->addFlags(new Required(), new CascadeDelete()),
            new OneToManyAssociationField('orderTransactions', OrderTransactionDefinition::class, 'state_id'),
            new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'state_id'),
            new OneToManyAssociationField('orders', OrderDefinition::class, 'state_id'),
            new OneToManyAssociationField('orderTransactionCaptures', OrderTransactionCaptureDefinition::class, 'state_id'),
            new OneToManyAssociationField('orderTransactionCaptureRefunds', OrderTransactionCaptureRefundDefinition::class, 'state_id'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            new OneToManyAssociationField('toStateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'to_state_id'),
            new OneToManyAssociationField('fromStateMachineHistoryEntries', StateMachineHistoryDefinition::class, 'from_state_id'),
        ]);
    }
}

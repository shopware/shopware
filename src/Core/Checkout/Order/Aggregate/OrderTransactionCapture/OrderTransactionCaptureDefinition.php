<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture;

use Shopware\Core\Checkout\Order\Aggregate\OrderRefund\OrderRefundDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class OrderTransactionCaptureDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'order_transaction_capture';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderTransactionCaptureCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderTransactionCaptureEntity::class;
    }

    public function since(): ?string
    {
        return '6.3.4.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrderTransactionDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('transaction_id', 'transactionId', OrderTransactionDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderTransactionDefinition::class, 'transaction_version_id'))->addFlags(new Required()),
            (new FloatField('amount', 'amount'))->addFlags(new Required()),
            new StringField('external_reference', 'externalReference'),

            (new StateMachineStateField('state_id', 'stateId', OrderTransactionCaptureStates::STATE_MACHINE))->addFlags(new Required()),
            new ManyToOneAssociationField('stateMachineState', 'state_id', StateMachineStateDefinition::class, 'id', true),
            new CustomFields(),
            new ManyToOneAssociationField('transaction', 'transaction_id', OrderTransactionDefinition::class, 'id', false),
            new OneToManyAssociationField('refunds', OrderRefundDefinition::class, 'transaction_capture_id'),
        ]);
    }
}

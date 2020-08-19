<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderRefund;

use Shopware\Core\Checkout\Order\Aggregate\OrderRefundPosition\OrderRefundPositionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

class OrderRefundDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'order_refund';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return OrderRefundCollection::class;
    }

    public function getEntityClass(): string
    {
        return OrderRefundEntity::class;
    }

    public function since(): ?string
    {
        return '6.3.4.0';
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrderDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new Required()),
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->addFlags(new Required()),
            (new FloatField('amount', 'amount'))->addFlags(new Required()),
            new JsonField('options', 'options'),

            new FkField('transaction_id', 'transactionId', OrderTransactionDefinition::class),
            (new ReferenceVersionField(OrderTransactionDefinition::class, 'transaction_version_id'))->addFlags(new Required()),

            (new StateMachineStateField('state_id', 'stateId', OrderRefundStates::STATE_MACHINE))->addFlags(new Required()),
            new ManyToOneAssociationField('stateMachineState', 'state_id', StateMachineStateDefinition::class, 'id', true),
            new CustomFields(),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('transaction', 'transaction_id', OrderTransactionDefinition::class, 'id', false),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, 'id', false),
            (new OneToManyAssociationField('positions', OrderRefundPositionDefinition::class, 'order_refund_id', 'id'))->addFlags(new CascadeDelete(), new SearchRanking(SearchRanking::ASSOCIATION_SEARCH_RANKING)),
            new FkField('transaction_capture_id', 'transactionCaptureId', OrderTransactionCaptureDefinition::class),
            new ManyToOneAssociationField('transactionCapture', 'transaction_capture_id', OrderTransactionCaptureDefinition::class, 'id', false),
        ]);
    }
}

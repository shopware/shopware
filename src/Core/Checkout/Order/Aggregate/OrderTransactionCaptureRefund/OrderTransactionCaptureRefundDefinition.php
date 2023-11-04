<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefund;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCapture\OrderTransactionCaptureDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionCaptureRefundPosition\OrderTransactionCaptureRefundPositionDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StateMachineStateField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateDefinition;

#[Package('customer-order')]
class OrderTransactionCaptureRefundDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'order_transaction_capture_refund';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function since(): ?string
    {
        return '6.4.12.0';
    }

    public function getEntityClass(): string
    {
        return OrderTransactionCaptureRefundEntity::class;
    }

    public function getCollectionClass(): string
    {
        return OrderTransactionCaptureRefundCollection::class;
    }

    protected function getParentDefinitionClass(): ?string
    {
        return OrderTransactionCaptureDefinition::class;
    }

    protected function defineFields(): FieldCollection
    {
        // @deprecated tag:v6.6.0 - Variable $autoload will be removed in the next major as it will be false by default
        $autoload = !Feature::isActive('v6.6.0.0');

        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('capture_id', 'captureId', OrderTransactionCaptureDefinition::class))->addFlags(new ApiAware(), new Required()),

            (new StateMachineStateField('state_id', 'stateId', OrderTransactionCaptureRefundStates::STATE_MACHINE))->addFlags(new ApiAware(), new Required()),
            (new ManyToOneAssociationField('stateMachineState', 'state_id', StateMachineStateDefinition::class, 'id', $autoload))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('transactionCapture', 'capture_id', OrderTransactionCaptureDefinition::class, 'id'))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('positions', OrderTransactionCaptureRefundPositionDefinition::class, 'refund_id'))->addFlags(new ApiAware(), new CascadeDelete()),

            (new StringField('external_reference', 'externalReference'))->addFlags(new ApiAware()),
            (new StringField('reason', 'reason'))->addFlags(new ApiAware()),
            (new CalculatedPriceField('amount', 'amount'))->addFlags(new ApiAware(), new Required()),
            (new CustomFields())->addFlags(new ApiAware()),
        ]);
    }
}

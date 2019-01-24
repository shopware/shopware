<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CalculatedPriceField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;

class OrderTransactionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order_transaction';
    }

    public static function getCollectionClass(): string
    {
        return OrderTransactionCollection::class;
    }

    public static function getEntityClass(): string
    {
        return OrderTransactionEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return OrderDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new Required()),
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->addFlags(new Required()),
            (new FkField('order_transaction_state_id', 'orderTransactionStateId', OrderTransactionStateDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderTransactionStateDefinition::class))->addFlags(new Required()),
            (new CalculatedPriceField('amount', 'amount'))->addFlags(new Required()),
            new JsonField('details', 'details'),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, false),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, false),
            (new ManyToOneAssociationField('orderTransactionState', 'order_transaction_state_id', OrderTransactionStateDefinition::class, false))->addFlags(new RestrictDelete()),
        ]);
    }
}

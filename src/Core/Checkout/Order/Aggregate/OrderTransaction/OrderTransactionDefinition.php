<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderTransaction;

use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\JsonField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ObjectField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;

class OrderTransactionDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order_transaction';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->setFlags(new Required()),
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(PaymentMethodDefinition::class))->setFlags(new Required()),
            (new FkField('order_transaction_state_id', 'orderTransactionStateId', OrderTransactionStateDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderTransactionStateDefinition::class))->setFlags(new Required()),
            (new ObjectField('amount', 'amount'))->setFlags(new Required()),
            new JsonField('details', 'details'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, false))->setFlags(new CascadeDelete()),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, false),
            (new ManyToOneAssociationField('orderTransactionState', 'order_transaction_state_id', OrderTransactionStateDefinition::class, false))->setFlags(new RestrictDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return OrderTransactionCollection::class;
    }

    public static function getStructClass(): string
    {
        return OrderTransactionStruct::class;
    }
}

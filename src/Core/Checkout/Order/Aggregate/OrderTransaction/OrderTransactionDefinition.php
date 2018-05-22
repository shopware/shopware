<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Aggregate\OrderTransaction;

use Shopware\Checkout\Order\Aggregate\OrderTransaction\Collection\OrderTransactionBasicCollection;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\Collection\OrderTransactionDetailCollection;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\Event\OrderTransactionDeletedEvent;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\Event\OrderTransactionWrittenEvent;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\Struct\OrderTransactionBasicStruct;
use Shopware\Checkout\Order\Aggregate\OrderTransaction\Struct\OrderTransactionDetailStruct;
use Shopware\Checkout\Order\Aggregate\OrderTransactionState\OrderTransactionStateDefinition;
use Shopware\Checkout\Order\OrderDefinition;
use Shopware\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\JsonObjectField;
use Shopware\Framework\ORM\Field\LongTextField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\VersionField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Framework\ORM\Write\Flag\Serialized;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;

class OrderTransactionDefinition extends EntityDefinition
{
    /**
     * @var FieldCollection
     */
    protected static $primaryKeys;

    /**
     * @var FieldCollection
     */
    protected static $fields;

    /**
     * @var EntityExtensionInterface[]
     */
    protected static $extensions = [];

    public static function getEntityName(): string
    {
        return 'order_transaction';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new FkField('order_id', 'orderId', OrderDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->setFlags(new Required()),
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(PaymentMethodDefinition::class))->setFlags(new Required()),
            (new FkField('order_transaction_state_id', 'orderTransactionStateId', OrderTransactionStateDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(OrderTransactionStateDefinition::class))->setFlags(new Required()),
            (new JsonObjectField('amount', 'amount'))->setFlags(new Required(), new Serialized()),
            (new LongTextField('payload', 'payload'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, false))->setFlags(new WriteOnly(), new CascadeDelete()),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, false),
            (new ManyToOneAssociationField('orderTransactionState', 'order_transaction_state_id', OrderTransactionStateDefinition::class, false))->setFlags(new RestrictDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return OrderTransactionRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return OrderTransactionBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return OrderTransactionDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return OrderTransactionWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return OrderTransactionBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return OrderTransactionDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return OrderTransactionDetailCollection::class;
    }
}

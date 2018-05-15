<?php declare(strict_types=1);

namespace Shopware\Checkout\Order\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\JsonObjectField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\Serialized;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Checkout\Order\Collection\OrderTransactionBasicCollection;
use Shopware\Checkout\Order\Collection\OrderTransactionDetailCollection;
use Shopware\Checkout\Order\Event\OrderTransaction\OrderTransactionDeletedEvent;
use Shopware\Checkout\Order\Event\OrderTransaction\OrderTransactionWrittenEvent;
use Shopware\Checkout\Order\Repository\OrderTransactionRepository;
use Shopware\Checkout\Order\Struct\OrderTransactionBasicStruct;
use Shopware\Checkout\Order\Struct\OrderTransactionDetailStruct;
use Shopware\Api\Payment\Definition\PaymentMethodDefinition;

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

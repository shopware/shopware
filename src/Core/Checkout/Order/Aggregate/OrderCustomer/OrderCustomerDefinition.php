<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderCustomer;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;

class OrderCustomerDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order_customer';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            new FkField('customer_id', 'customerId', CustomerDefinition::class),
            new ReferenceVersionField(CustomerDefinition::class),

            (new StringField('email', 'email'))->setFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, false))->setFlags(new SearchRanking(0.5)),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'order_customer_id', false, 'id'))->setFlags(new RestrictDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return OrderCustomerCollection::class;
    }

    public static function getStructClass(): string
    {
        return OrderCustomerStruct::class;
    }
}

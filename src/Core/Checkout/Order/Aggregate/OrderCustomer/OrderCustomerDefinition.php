<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderCustomer;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;

class OrderCustomerDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'order_customer';
    }

    public static function getCollectionClass(): string
    {
        return OrderCustomerCollection::class;
    }

    public static function getEntityClass(): string
    {
        return OrderCustomerEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return OrderDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            new FkField('customer_id', 'customerId', CustomerDefinition::class),

            (new StringField('email', 'email'))->setFlags(new Required()),
            (new StringField('first_name', 'firstName'))->setFlags(new Required()),
            (new StringField('last_name', 'lastName'))->setFlags(new Required()),
            new StringField('salutation', 'salutation'),
            new StringField('title', 'title'),
            new StringField('customer_number', 'customerNumber'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, false))->setFlags(new SearchRanking(0.5)),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'order_customer_id', false, 'id'))->setFlags(new RestrictDelete()),
        ]);
    }
}

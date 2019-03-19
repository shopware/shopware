<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Order\Aggregate\OrderCustomer;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\VersionField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Salutation\SalutationDefinition;

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
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            new FkField('customer_id', 'customerId', CustomerDefinition::class),

            (new StringField('email', 'email'))->addFlags(new Required()),
            (new FkField('salutation_id', 'salutationId', SalutationDefinition::class))->addFlags(new Required()),
            (new StringField('first_name', 'firstName'))->addFlags(new Required()),
            (new StringField('last_name', 'lastName'))->addFlags(new Required()),
            new StringField('title', 'title'),
            new StringField('customer_number', 'customerNumber'),
            new AttributesField(),
            new CreatedAtField(),
            new UpdatedAtField(),
            new OneToOneAssociationField('order', 'id', 'order_customer_id', OrderDefinition::class, false),
            (new ManyToOneAssociationField('customer', 'customer_id', CustomerDefinition::class, false))->addFlags(new SearchRanking(0.5)),
            new ManyToOneAssociationField('salutation', 'salutation_id', SalutationDefinition::class, true),
        ]);
    }
}

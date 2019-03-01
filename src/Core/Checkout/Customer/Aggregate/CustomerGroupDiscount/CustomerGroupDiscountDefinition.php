<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\AttributesField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class CustomerGroupDiscountDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'customer_group_discount';
    }

    public static function getCollectionClass(): string
    {
        return CustomerGroupDiscountCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CustomerGroupDiscountEntity::class;
    }

    public static function getParentDefinitionClass(): ?string
    {
        return CustomerGroupDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('customer_group_id', 'customerGroupId', CustomerGroupDefinition::class))->addFlags(new Required()),
            (new FloatField('percentage_discount', 'percentageDiscount'))->addFlags(new Required()),
            (new FloatField('minimum_cart_amount', 'minimumCartAmount'))->addFlags(new Required()),
            new AttributesField(),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('customerGroup', 'customer_group_id', CustomerGroupDefinition::class, false),
        ]);
    }
}

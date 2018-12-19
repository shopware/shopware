<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount\CustomerGroupDiscountDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\SearchRanking;

class CustomerGroupDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'customer_group';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new TranslatedField('name'))->setFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new BoolField('display_gross', 'displayGross'),
            new BoolField('input_gross', 'inputGross'),
            new BoolField('has_global_discount', 'hasGlobalDiscount'),
            new FloatField('percentage_global_discount', 'percentageGlobalDiscount'),
            new FloatField('minimum_order_amount', 'minimumOrderAmount'),
            new FloatField('minimum_order_amount_surcharge', 'minimumOrderAmountSurcharge'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'customer_group_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('discounts', CustomerGroupDiscountDefinition::class, 'customer_group_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(CustomerGroupTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return CustomerGroupCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CustomerGroupEntity::class;
    }
}

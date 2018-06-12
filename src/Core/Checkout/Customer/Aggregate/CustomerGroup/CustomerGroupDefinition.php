<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupBasicCollection;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupBasicStruct;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupDiscount\CustomerGroupDiscountDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FloatField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\VersionField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\RestrictDelete;
use Shopware\Core\Framework\ORM\Write\Flag\SearchRanking;
use Shopware\Core\Framework\ORM\Write\Flag\WriteOnly;

class CustomerGroupDefinition extends EntityDefinition
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
        return 'customer_group';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new BoolField('display_gross', 'displayGross'),
            new BoolField('input_gross', 'inputGross'),
            new BoolField('has_global_discount', 'hasGlobalDiscount'),
            new FloatField('percentage_global_discount', 'percentageGlobalDiscount'),
            new FloatField('minimum_order_amount', 'minimumOrderAmount'),
            new FloatField('minimum_order_amount_surcharge', 'minimumOrderAmountSurcharge'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'customer_group_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('discounts', CustomerGroupDiscountDefinition::class, 'customer_group_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('translations', CustomerGroupTranslationDefinition::class, 'customer_group_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, 'customer_group_id', false, 'id'))->setFlags(new WriteOnly()),
            (new OneToManyAssociationField('taxAreaRules', \Shopware\Core\System\Tax\Aggregate\TaxAreaRule\TaxAreaRuleDefinition::class, 'customer_group_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
        ]);
    }

    public static function getBasicCollectionClass(): string
    {
        return CustomerGroupBasicCollection::class;
    }

    public static function getBasicStructClass(): string
    {
        return CustomerGroupBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CustomerGroupTranslationDefinition::class;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Checkout\Customer\Definition;

use Shopware\Checkout\Customer\Collection\CustomerGroupBasicCollection;
use Shopware\Checkout\Customer\Collection\CustomerGroupDetailCollection;
use Shopware\Checkout\Customer\Event\CustomerGroup\CustomerGroupDeletedEvent;
use Shopware\Checkout\Customer\Event\CustomerGroup\CustomerGroupWrittenEvent;
use Shopware\Checkout\Customer\Repository\CustomerGroupRepository;
use Shopware\Checkout\Customer\Struct\CustomerGroupBasicStruct;
use Shopware\Checkout\Customer\Struct\CustomerGroupDetailStruct;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Shipping\Definition\ShippingMethodDefinition;
use Shopware\Api\Shop\Definition\ShopDefinition;
use Shopware\System\Tax\Definition\TaxAreaRuleDefinition;

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

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
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
            (new OneToManyAssociationField('shops', ShopDefinition::class, 'customer_group_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('taxAreaRules', TaxAreaRuleDefinition::class, 'customer_group_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return CustomerGroupRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return CustomerGroupBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return CustomerGroupDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return CustomerGroupWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return CustomerGroupBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return CustomerGroupTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return CustomerGroupDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return CustomerGroupDetailCollection::class;
    }
}

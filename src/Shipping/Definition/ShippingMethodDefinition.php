<?php declare(strict_types=1);

namespace Shopware\Shipping\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TranslatedField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Customer\Definition\CustomerGroupDefinition;
use Shopware\Order\Definition\OrderDeliveryDefinition;
use Shopware\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Shipping\Collection\ShippingMethodDetailCollection;
use Shopware\Shipping\Event\ShippingMethod\ShippingMethodWrittenEvent;
use Shopware\Shipping\Repository\ShippingMethodRepository;
use Shopware\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Shipping\Struct\ShippingMethodDetailStruct;
use Shopware\Shop\Definition\ShopDefinition;

class ShippingMethodDefinition extends EntityDefinition
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
        return 'shipping_method';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('customer_group_uuid', 'customerGroupUuid', CustomerGroupDefinition::class),
            (new IntField('type', 'type'))->setFlags(new Required()),
            (new BoolField('bind_shippingfree', 'bindShippingfree'))->setFlags(new Required()),
            (new BoolField('bind_laststock', 'bindLaststock'))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            new BoolField('active', 'active'),
            new IntField('position', 'position'),
            new IntField('calculation', 'calculation'),
            new IntField('surcharge_calculation', 'surchargeCalculation'),
            new IntField('tax_calculation', 'taxCalculation'),
            new FloatField('shipping_free', 'shippingFree'),
            new IntField('bind_time_from', 'bindTimeFrom'),
            new IntField('bind_time_to', 'bindTimeTo'),
            new BoolField('bind_instock', 'bindInstock'),
            new IntField('bind_weekday_from', 'bindWeekdayFrom'),
            new IntField('bind_weekday_to', 'bindWeekdayTo'),
            new FloatField('bind_weight_from', 'bindWeightFrom'),
            new FloatField('bind_weight_to', 'bindWeightTo'),
            new FloatField('bind_price_from', 'bindPriceFrom'),
            new FloatField('bind_price_to', 'bindPriceTo'),
            new LongTextField('bind_sql', 'bindSql'),
            new LongTextField('status_link', 'statusLink'),
            new LongTextField('calculation_sql', 'calculationSql'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new TranslatedField(new LongTextField('description', 'description')),
            new TranslatedField(new StringField('comment', 'comment')),
            new ManyToOneAssociationField('customerGroup', 'customer_group_uuid', CustomerGroupDefinition::class, false),
            new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'shipping_method_uuid', false, 'uuid'),
            new OneToManyAssociationField('prices', ShippingMethodPriceDefinition::class, 'shipping_method_uuid', true, 'uuid'),
            (new TranslationsAssociationField('translations', ShippingMethodTranslationDefinition::class, 'shipping_method_uuid', false, 'uuid'))->setFlags(new Required()),
            new OneToManyAssociationField('shops', ShopDefinition::class, 'shipping_method_uuid', false, 'uuid'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ShippingMethodRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ShippingMethodBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ShippingMethodWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ShippingMethodBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return ShippingMethodTranslationDefinition::class;
    }

    public static function getDetailStructClass(): string
    {
        return ShippingMethodDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ShippingMethodDetailCollection::class;
    }
}

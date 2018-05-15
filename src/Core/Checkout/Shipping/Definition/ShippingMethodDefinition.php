<?php declare(strict_types=1);

namespace Shopware\Checkout\Shipping\Definition;

use Shopware\Api\Application\Definition\ApplicationDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FloatField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
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
use Shopware\Checkout\Order\Definition\OrderDeliveryDefinition;
use Shopware\Checkout\Shipping\Collection\ShippingMethodBasicCollection;
use Shopware\Checkout\Shipping\Collection\ShippingMethodDetailCollection;
use Shopware\Checkout\Shipping\Event\ShippingMethod\ShippingMethodDeletedEvent;
use Shopware\Checkout\Shipping\Event\ShippingMethod\ShippingMethodWrittenEvent;
use Shopware\Checkout\Shipping\Repository\ShippingMethodRepository;
use Shopware\Checkout\Shipping\Struct\ShippingMethodBasicStruct;
use Shopware\Checkout\Shipping\Struct\ShippingMethodDetailStruct;
use Shopware\Api\Shop\Definition\ShopDefinition;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),
            (new IntField('type', 'type'))->setFlags(new Required()),
            (new BoolField('bind_shippingfree', 'bindShippingfree'))->setFlags(new Required()),
            (new BoolField('bind_laststock', 'bindLaststock'))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new BoolField('active', 'active'),
            new IntField('position', 'position'),
            new IntField('calculation', 'calculation'),
            new IntField('surcharge_calculation', 'surchargeCalculation'),
            new IntField('tax_calculation', 'taxCalculation'),
            new IntField('min_delivery_time', 'minDeliveryTime'),
            new IntField('max_delivery_time', 'maxDeliveryTime'),
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
            (new OneToManyAssociationField('applications', ApplicationDefinition::class, 'shipping_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new TranslatedField(new LongTextField('description', 'description')))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new TranslatedField(new StringField('comment', 'comment')))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'shipping_method_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('prices', ShippingMethodPriceDefinition::class, 'shipping_method_id', true, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('translations', ShippingMethodTranslationDefinition::class, 'shipping_method_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            (new OneToManyAssociationField('shops', ShopDefinition::class, 'shipping_method_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
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

    public static function getDeletedEventClass(): string
    {
        return ShippingMethodDeletedEvent::class;
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

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\LongTextField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
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
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class ShippingMethodDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'shipping_method';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new IntField('type', 'type'))->setFlags(new Required()),
            (new BoolField('bind_shippingfree', 'bindShippingfree'))->setFlags(new Required()),
            new BoolField('bind_laststock', 'bindLaststock'),
            (new TranslatedField('name'))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
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
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'shipping_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new TranslatedField('description'))->setFlags(new SearchRanking(self::LOW_SEARCH_RAKING)),
            (new TranslatedField('comment'))->setFlags(new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'shipping_method_id', false, 'id'))->setFlags(new RestrictDelete()),
            (new OneToManyAssociationField('prices', ShippingMethodPriceDefinition::class, 'shipping_method_id', true, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ShippingMethodTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelShippingMethodDefinition::class, false, 'shipping_method_id', 'sales_channel_id'),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return ShippingMethodCollection::class;
    }

    public static function getStructClass(): string
    {
        return ShippingMethodEntity::class;
    }
}

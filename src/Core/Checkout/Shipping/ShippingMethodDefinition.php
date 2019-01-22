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

    public static function getCollectionClass(): string
    {
        return ShippingMethodCollection::class;
    }

    public static function getEntityClass(): string
    {
        return ShippingMethodEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new IntField('type', 'type'))->addFlags(new Required()),
            (new BoolField('bind_shippingfree', 'bindShippingfree'))->addFlags(new Required()),
            new BoolField('bind_laststock', 'bindLaststock'),
            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
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
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'shipping_method_id', false, 'id'))->addFlags(new RestrictDelete()),
            (new TranslatedField('description'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            (new TranslatedField('comment'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'shipping_method_id', false, 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('prices', ShippingMethodPriceDefinition::class, 'shipping_method_id', true, 'id'))->addFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ShippingMethodTranslationDefinition::class, 'shipping_method_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelShippingMethodDefinition::class, false, 'shipping_method_id', 'sales_channel_id'),
        ]);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Shipping;

use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPriceRule\ShippingMethodPriceRuleDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodRules\ShippingMethodRuleDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopware\Core\Content\Rule\RuleDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\EntityExistence;
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

    public static function getDefaults(EntityExistence $existence): array
    {
        if ($existence->exists()) {
            return [];
        }

        return [
            'availabilityRuleIds' => [],
        ];
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new BoolField('bind_shippingfree', 'bindShippingfree'))->addFlags(new Required()),
            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            new BoolField('active', 'active'),
            new IntField('min_delivery_time', 'minDeliveryTime'),
            new IntField('max_delivery_time', 'maxDeliveryTime'),
            new FloatField('shipping_free', 'shippingFree'),
            new TranslatedField('attributes'),
            new ListField('availability_rule_ids', 'availabilityRuleIds', IdField::class),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'shipping_method_id', false, 'id'))->addFlags(new RestrictDelete()),
            (new TranslatedField('description'))->addFlags(new SearchRanking(SearchRanking::LOW_SEARCH_RAKING)),
            (new TranslatedField('comment'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new OneToManyAssociationField('orderDeliveries', OrderDeliveryDefinition::class, 'shipping_method_id', false, 'id'))->addFlags(new RestrictDelete()),
            (new TranslationsAssociationField(ShippingMethodTranslationDefinition::class, 'shipping_method_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelShippingMethodDefinition::class, false, 'shipping_method_id', 'sales_channel_id'),
            (new ManyToManyAssociationField('availabilityRules', RuleDefinition::class, ShippingMethodRuleDefinition::class, false, 'shipping_method_id', 'rule_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('priceRules', ShippingMethodPriceRuleDefinition::class, 'shipping_method_id', true, 'id'))->addFlags(new CascadeDelete()),
        ]);
    }
}

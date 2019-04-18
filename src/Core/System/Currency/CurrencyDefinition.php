<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency;

use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodPrice\ShippingMethodPriceDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductPrice\ProductPriceDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Deferred;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\SearchRanking;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FloatField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopware\Core\System\Currency\SalesChannel\SalesChannelCurrencyDefinition as SalesChannelApiCurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;

class CurrencyDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'currency';
    }

    public static function getCollectionClass(): string
    {
        return CurrencyCollection::class;
    }

    public static function getEntityClass(): string
    {
        return CurrencyEntity::class;
    }

    public static function getSalesChannelDecorationDefinition(): string
    {
        return SalesChannelApiCurrencyDefinition::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FloatField('factor', 'factor'))->addFlags(new Required()),
            (new StringField('symbol', 'symbol'))->addFlags(new Required()),
            (new TranslatedField('shortName'))->addFlags(new SearchRanking(SearchRanking::MIDDLE_SEARCH_RANKING)),
            (new TranslatedField('name'))->addFlags(new SearchRanking(SearchRanking::HIGH_SEARCH_RANKING)),
            (new IntField('decimal_precision', 'decimalPrecision'))->addFlags(new Required()),
            new IntField('position', 'position'),
            (new BoolField('is_default', 'isDefault'))->addFlags(new Deferred()),
            new TranslatedField('attributes'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'currency_id', 'id'))->addFlags(new RestrictDelete()),
            (new TranslationsAssociationField(CurrencyTranslationDefinition::class, 'currency_id'))->addFlags(new Required()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'currency_id', 'id'))->addFlags(new RestrictDelete()),
            new OneToManyAssociationField('productPrices', ProductPriceDefinition::class, 'currency_id', 'id'),
            new OneToManyAssociationField('shippingMethodPrices', ShippingMethodPriceDefinition::class, 'currency_id', 'id'),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelCurrencyDefinition::class, 'currency_id', 'sales_channel_id'),
            (new OneToManyAssociationField('salesChannelDomains', SalesChannelDomainDefinition::class, 'currency_id'))->addFlags(new RestrictDelete()),
        ]);
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Catalog\CatalogDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCatalog\SalesChannelCatalogDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;

class SalesChannelDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'sales_channel';
    }

    public static function getCollectionClass(): string
    {
        return SalesChannelCollection::class;
    }

    public static function getEntityClass(): string
    {
        return SalesChannelEntity::class;
    }

    protected static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('type_id', 'typeId', SalesChannelTypeDefinition::class))->setFlags(new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->setFlags(new Required()),
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),
            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->setFlags(new Required()),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new Required()),
            (new StringField('type', 'type'))->setFlags(new Required()),
            new TranslatedField('name'),
            (new StringField('access_key', 'accessKey'))->setFlags(new Required()),
            new JsonField('configuration', 'configuration'),
            new BoolField('active', 'active'),
            new StringField('tax_calculation_type', 'taxCalculationType'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslationsAssociationField(SalesChannelTranslationDefinition::class))->setFlags(new Required(), new CascadeDelete()),
            new ManyToManyAssociationField('catalogs', CatalogDefinition::class, SalesChannelCatalogDefinition::class, true, 'sales_channel_id', 'catalog_id'),
            new ManyToManyAssociationField('currencies', CurrencyDefinition::class, SalesChannelCurrencyDefinition::class, false, 'sales_channel_id', 'currency_id'),
            new ManyToManyAssociationField('languages', LanguageDefinition::class, SalesChannelLanguageDefinition::class, false, 'sales_channel_id', 'language_id'),
            new ManyToManyAssociationField('countries', CountryDefinition::class, SalesChannelCountryDefinition::class, false, 'sales_channel_id', 'country_id'),
            new ManyToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, SalesChannelPaymentMethodDefinition::class, false, 'sales_channel_id', 'payment_method_id'),
            new ManyToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, SalesChannelShippingMethodDefinition::class, false, 'sales_channel_id', 'shipping_method_id'),
            new ManyToOneAssociationField('type', 'type_id', SalesChannelTypeDefinition::class, true),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, true),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, true),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, false),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, false),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, false),
            new OneToManyAssociationField('orders', OrderDefinition::class, 'sales_channel_id', false, 'id'),
            new OneToManyAssociationField('customers', CustomerDefinition::class, 'sales_channel_id', false, 'id'),
        ]);
    }
}

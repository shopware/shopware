<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\BoolField;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\JsonField;
use Shopware\Core\Framework\ORM\Field\ListField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\PasswordField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslatedField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;

class SalesChannelDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'sales_channel';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(CurrencyDefinition::class),
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(PaymentMethodDefinition::class),
            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(ShippingMethodDefinition::class),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(CountryDefinition::class),
            (new StringField('type', 'type'))->setFlags(new Required()),
            (new TranslatedField(new StringField('name', 'name')))->setFlags(new Required()),
            (new StringField('access_key', 'accessKey'))->setFlags(new Required()),
            (new PasswordField('secret_access_key', 'secretAccessKey'))->setFlags(new Required()),
            (new ListField('catalog_ids', 'catalogIds', IdField::class))->setFlags(new Required()),
            (new ListField('currency_ids', 'currencyIds', IdField::class))->setFlags(new Required()),
            (new ListField('language_ids', 'languageIds', IdField::class))->setFlags(new Required()),
            new JsonField('configuration', 'configuration'),
            new BoolField('active', 'active'),
            new StringField('tax_calculation_type', 'taxCalculationType'),
            new CreatedAtField(),
            new UpdatedAtField(),
            (new TranslationsAssociationField('translations', SalesChannelTranslationDefinition::class, 'sales_channel_id', false, 'id'))->setFlags(new Required(), new CascadeDelete()),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, true),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, true),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, false),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, false),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, false),
            new OneToManyAssociationField('orders', OrderDefinition::class, 'sales_channel_id', false, 'id'),
            new OneToManyAssociationField('customers', CustomerDefinition::class, 'sales_channel_id', false, 'id'),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return SalesChannelCollection::class;
    }

    public static function getStructClass(): string
    {
        return SalesChannelStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return SalesChannelTranslationDefinition::class;
    }
}

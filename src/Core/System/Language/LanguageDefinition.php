<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopware\Core\Checkout\DiscountSurcharge\Aggregate\DiscountSurchargeTranslation\DiscountSurchargeTranslationDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\OrderStateTranslationDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationDefinition;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopware\Core\Content\Catalog\Aggregate\CatalogTranslation\CatalogTranslationDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationDefinition;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\ConfigurationGroupTranslationDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CreatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TenantIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\Search\SearchDocumentDefinition;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\ListingFacetTranslationDefinition;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\ListingSortingTranslationDefinition;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;

class LanguageDefinition extends EntityDefinition
{
    public static function getEntityName(): string
    {
        return 'language';
    }

    public static function defineFields(): FieldCollection
    {
        return new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new ParentField(self::class),
            new FkField('locale_id', 'localeId', LocaleDefinition::class),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ParentAssociationField(self::class, false),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, true),
            new ChildrenAssociationField(self::class),
            new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'language_id', false, 'id'),
            new OneToManyAssociationField('snippets', SnippetDefinition::class, 'language_id', false, 'id'),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelLanguageDefinition::class, false, 'language_id', 'sales_channel_id'),
            (new TranslationsAssociationField(CatalogTranslationDefinition::class, 'catalogTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(CategoryTranslationDefinition::class, 'categoryTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(CountryStateTranslationDefinition::class, 'countryStateTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(CountryTranslationDefinition::class, 'countryTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(CurrencyTranslationDefinition::class, 'currencyTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(CustomerGroupTranslationDefinition::class, 'customerGroupTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ListingFacetTranslationDefinition::class, 'listingFacetTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ListingSortingTranslationDefinition::class, 'listingSortingTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(LocaleTranslationDefinition::class, 'localeTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(MediaTranslationDefinition::class, 'mediaTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(OrderStateTranslationDefinition::class, 'orderStateTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(OrderTransactionStateTranslationDefinition::class, 'orderTransactionStateTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(PaymentMethodTranslationDefinition::class, 'paymentMethodTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ProductManufacturerTranslationDefinition::class, 'productManufacturerTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ProductTranslationDefinition::class, 'productTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ShippingMethodTranslationDefinition::class, 'shippingMethodTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(UnitTranslationDefinition::class, 'unitTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ConfigurationGroupTranslationDefinition::class, 'configurationGroupTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(ConfigurationGroupOptionTranslationDefinition::class, 'configurationGroupOptionTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(DiscountSurchargeTranslationDefinition::class, 'discountsurchargeTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(SalesChannelTranslationDefinition::class, 'salesChannelTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(SalesChannelTypeTranslationDefinition::class, 'salesChannelTypeTranslations'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField(SearchDocumentDefinition::class, 'searchDocuments'))->setFlags(new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return LanguageCollection::class;
    }

    public static function getStructClass(): string
    {
        return LanguageStruct::class;
    }
}

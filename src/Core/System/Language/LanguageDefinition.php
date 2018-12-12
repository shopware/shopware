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
use Shopware\Core\Content\Media\Aggregate\MediaFolderTranslation\MediaFolderTranslationDefinition;
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
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\UpdatedAtField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Write\Flag\Required;
use Shopware\Core\Framework\Search\SearchDocumentDefinition;
use Shopware\Core\Framework\Snippet\SnippetDefinition;
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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new ParentFkField(self::class),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->setFlags(new Required()),
            new FkField('translation_code_id', 'translationCodeId', LocaleDefinition::class),

            (new StringField('name', 'name'))->setFlags(new Required()),

            new CreatedAtField(),
            new UpdatedAtField(),
            new ParentAssociationField(self::class, false),

            (new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, true)),
            new ManyToOneAssociationField('translationCode', 'translation_code_id', LocaleDefinition::class, true),

            new ChildrenAssociationField(self::class),
            new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelLanguageDefinition::class, false, 'language_id', 'sales_channel_id'),
            new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'language_id', false, 'id'),
            new OneToManyAssociationField('snippets', SnippetDefinition::class, 'language_id', false, 'id'),

            (new OneToManyAssociationField('catalogTranslations', CatalogTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('categoryTranslations', CategoryTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('countryStateTranslations', CountryStateTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('countryTranslations', CountryTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('currencyTranslations', CurrencyTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('customerGroupTranslations', CustomerGroupTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('listingFacetTranslations', ListingFacetTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('listingSortingTranslations', ListingSortingTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('localeTranslations', LocaleTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mediaTranslations', MediaTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('orderStateTranslations', OrderStateTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('orderTransactionStateTranslations', OrderTransactionStateTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('paymentMethodTranslations', PaymentMethodTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productManufacturerTranslations', ProductManufacturerTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productTranslations', ProductTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('shippingMethodTranslations', ShippingMethodTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('unitTranslations', UnitTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('configurationGroupTranslations', ConfigurationGroupTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('configurationGroupOptionTranslations', ConfigurationGroupOptionTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('discountSurchargeTranslations', DiscountSurchargeTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('salesChannelTranslations', SalesChannelTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('salesChannelTypeTranslations', SalesChannelTypeTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('searchDocuments', SearchDocumentDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mediaFolderTranslations', MediaFolderTranslationDefinition::class, 'language_id', false))->setFlags(new CascadeDelete()),
        ]);
    }

    public static function getCollectionClass(): string
    {
        return LanguageCollection::class;
    }

    public static function getEntityClass(): string
    {
        return LanguageEntity::class;
    }
}

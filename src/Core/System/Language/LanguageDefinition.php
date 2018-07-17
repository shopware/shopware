<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\OrderStateTranslationDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransactionStateTranslation\OrderTransactionStateTranslationDefinition;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupOptionTranslation\ConfigurationGroupOptionTranslationDefinition;
use Shopware\Core\Content\Configuration\Aggregate\ConfigurationGroupTranslation\ConfigurationGroupTranslationDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\MediaAlbumTranslationDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\Field\ChildrenAssociationField;
use Shopware\Core\Framework\ORM\Field\CreatedAtField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\OneToManyAssociationField;
use Shopware\Core\Framework\ORM\Field\ParentField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\Field\UpdatedAtField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\CountryAreaTranslationDefinition;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopware\Core\System\Listing\Aggregate\ListingFacetTranslation\ListingFacetTranslationDefinition;
use Shopware\Core\System\Listing\Aggregate\ListingSortingTranslation\ListingSortingTranslationDefinition;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Snippet\SnippetDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\TaxAreaRuleTranslationDefinition;
use Shopware\Core\System\Touchpoint\TouchpointDefinition;
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
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(LocaleDefinition::class),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new CreatedAtField(),
            new UpdatedAtField(),
            new ManyToOneAssociationField('parent', 'parent_id', LanguageDefinition::class, false),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, true),
            new ChildrenAssociationField(self::class),
            new OneToManyAssociationField('touchpoints', TouchpointDefinition::class, 'language_id', false, 'id'),
            new OneToManyAssociationField('snippets', SnippetDefinition::class, 'language_id', false, 'id'),
            (new TranslationsAssociationField('categoryTranslations', CategoryTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('countryAreaTranslations', CountryAreaTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('countryStateTranslations', CountryStateTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('countryTranslations', CountryTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('currencyTranslations', CurrencyTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('customerGroupTranslations', CustomerGroupTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('listingFacetTranslations', ListingFacetTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('listingSortingTranslations', ListingSortingTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('localeTranslations', LocaleTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('mediaAlbumTranslations', MediaAlbumTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('mediaTranslations', MediaTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('orderStateTranslations', OrderStateTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('orderTransactionStateTranslations', OrderTransactionStateTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('paymentMethodTranslations', PaymentMethodTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('productManufacturerTranslations', ProductManufacturerTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('productTranslations', ProductTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('shippingMethodTranslations', ShippingMethodTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('taxAreaRuleTranslations', TaxAreaRuleTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('unitTranslations', UnitTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('configurationGroupTranslations', ConfigurationGroupTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('configurationGroupOptionTranslations', ConfigurationGroupOptionTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
            (new TranslationsAssociationField('productSearchKeywords', ProductSearchKeywordDefinition::class, 'language_id', false, 'id'))->setFlags(new CascadeDelete()),
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

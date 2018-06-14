<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Framework\ORM\EntityDefinition;
use Shopware\Core\Framework\ORM\EntityExtensionInterface;
use Shopware\Core\Framework\ORM\Field\ChildrenAssociationField;
use Shopware\Core\Framework\ORM\Field\DateField;
use Shopware\Core\Framework\ORM\Field\FkField;
use Shopware\Core\Framework\ORM\Field\IdField;
use Shopware\Core\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\ORM\Field\ParentField;
use Shopware\Core\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Core\Framework\ORM\Field\StringField;
use Shopware\Core\Framework\ORM\Field\TenantIdField;
use Shopware\Core\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Core\Framework\ORM\FieldCollection;
use Shopware\Core\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Core\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Core\Framework\ORM\Write\Flag\Required;
use Shopware\Core\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Core\System\Country\Aggregate\CountryAreaTranslation\CountryAreaTranslationDefinition;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Shopware\Core\System\Language\Collection\LanguageBasicCollection;
use Shopware\Core\System\Language\Collection\LanguageDetailCollection;
use Shopware\Core\System\Language\Event\LanguageDeletedEvent;
use Shopware\Core\System\Language\Event\LanguageWrittenEvent;
use Shopware\Core\System\Language\Struct\LanguageBasicStruct;
use Shopware\Core\System\Language\Struct\LanguageDetailStruct;
use Shopware\Core\System\Listing\Definition\ListingFacetTranslationDefinition;
use Shopware\Core\System\Listing\Definition\ListingSortingTranslationDefinition;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxAreaRuleTranslation\TaxAreaRuleTranslationDefinition;
use Shopware\Core\System\Unit\Definition\UnitTranslationDefinition;

class LanguageDefinition extends EntityDefinition
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
        return 'language';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new ParentField(self::class),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->setFlags(new Required()),
            new ReferenceVersionField(LocaleDefinition::class),
            (new StringField('name', 'name'))->setFlags(new Required()),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('parent', 'parent_id', LanguageDefinition::class, false),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, true),
            new ChildrenAssociationField(self::class),
            (new TranslationsAssociationField('categoryTranslations', \Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('countryAreaTranslations', CountryAreaTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('countryStateTranslations', CountryStateTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('countryTranslations', \Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('currencyTranslations', \Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('customerGroupTranslations', CustomerGroupTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('listingFacetTranslations', ListingFacetTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('listingSortingTranslations', ListingSortingTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('localeTranslations', LocaleTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('mailTranslations', \Shopware\Core\System\Mail\Aggregate\MailTranslation\MailTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('mediaAlbumTranslations', \Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\MediaAlbumTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('mediaTranslations', \Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('orderStateTranslations', \Shopware\Core\Checkout\Order\Aggregate\OrderStateTranslation\OrderStateTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('paymentMethodTranslations', PaymentMethodTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('productManufacturerTranslations', \Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('productTranslations', ProductTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('shippingMethodTranslations', ShippingMethodTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('taxAreaRuleTranslations', TaxAreaRuleTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('unitTranslations', UnitTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return LanguageRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return LanguageBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return LanguageDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return LanguageWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return LanguageBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return LanguageDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return LanguageDetailCollection::class;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Application\Language\Definition;

use Shopware\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\System\Country\Aggregate\CountryAreaTranslation\CountryAreaTranslationDefinition;
use Shopware\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Shopware\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopware\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopware\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopware\Framework\ORM\EntityDefinition;
use Shopware\Framework\ORM\EntityExtensionInterface;
use Shopware\Framework\ORM\Field\ChildrenAssociationField;
use Shopware\Framework\ORM\Field\DateField;
use Shopware\Framework\ORM\Field\FkField;
use Shopware\Framework\ORM\Field\IdField;
use Shopware\Framework\ORM\Field\ManyToOneAssociationField;
use Shopware\Framework\ORM\Field\ParentField;
use Shopware\Framework\ORM\Field\ReferenceVersionField;
use Shopware\Framework\ORM\Field\StringField;
use Shopware\Framework\ORM\Field\TenantIdField;
use Shopware\Framework\ORM\Field\TranslationsAssociationField;
use Shopware\Framework\ORM\FieldCollection;
use Shopware\Framework\ORM\Write\Flag\CascadeDelete;
use Shopware\Framework\ORM\Write\Flag\PrimaryKey;
use Shopware\Framework\ORM\Write\Flag\Required;
use Shopware\Framework\ORM\Write\Flag\WriteOnly;
use Shopware\Application\Language\Collection\LanguageBasicCollection;
use Shopware\Application\Language\Collection\LanguageDetailCollection;
use Shopware\Application\Language\Event\Language\LanguageDeletedEvent;
use Shopware\Application\Language\Event\Language\LanguageWrittenEvent;
use Shopware\Application\Language\Repository\LanguageRepository;
use Shopware\Application\Language\Struct\LanguageBasicStruct;
use Shopware\Application\Language\Struct\LanguageDetailStruct;
use Shopware\System\Listing\Definition\ListingFacetTranslationDefinition;
use Shopware\System\Listing\Definition\ListingSortingTranslationDefinition;
use Shopware\System\Locale\LocaleDefinition;
use Shopware\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Shopware\System\Mail\Aggregate\MailTranslation\MailTranslationDefinition;
use Shopware\Content\Media\Aggregate\MediaAlbumTranslation\MediaAlbumTranslationDefinition;
use Shopware\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Checkout\Order\Aggregate\OrderStateTranslation\OrderStateTranslationDefinition;
use Shopware\Checkout\Payment\Definition\PaymentMethodTranslationDefinition;
use Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Checkout\Shipping\Definition\ShippingMethodTranslationDefinition;
use Shopware\System\Tax\Aggregate\TaxAreaRuleTranslation\TaxAreaRuleTranslationDefinition;
use Shopware\System\Unit\Definition\UnitTranslationDefinition;

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
            (new TranslationsAssociationField('categoryTranslations', \Shopware\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('countryAreaTranslations', CountryAreaTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('countryStateTranslations', CountryStateTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('countryTranslations', \Shopware\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('currencyTranslations', \Shopware\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('customerGroupTranslations', CustomerGroupTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('listingFacetTranslations', ListingFacetTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('listingSortingTranslations', ListingSortingTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('localeTranslations', LocaleTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('mailTranslations', \Shopware\System\Mail\Aggregate\MailTranslation\MailTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('mediaAlbumTranslations', \Shopware\Content\Media\Aggregate\MediaAlbumTranslation\MediaAlbumTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('mediaTranslations', \Shopware\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('orderStateTranslations', \Shopware\Checkout\Order\Aggregate\OrderStateTranslation\OrderStateTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('paymentMethodTranslations', PaymentMethodTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('productManufacturerTranslations', \Shopware\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
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

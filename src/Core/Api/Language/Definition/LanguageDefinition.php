<?php declare(strict_types=1);

namespace Shopware\Api\Language\Definition;

use Shopware\Content\Category\Definition\CategoryTranslationDefinition;
use Shopware\System\Country\Definition\CountryAreaTranslationDefinition;
use Shopware\System\Country\Definition\CountryStateTranslationDefinition;
use Shopware\System\Country\Definition\CountryTranslationDefinition;
use Shopware\System\Currency\Definition\CurrencyTranslationDefinition;
use Shopware\Api\Customer\Definition\CustomerGroupTranslationDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\ChildrenAssociationField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ParentField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\TranslationsAssociationField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Language\Collection\LanguageBasicCollection;
use Shopware\Api\Language\Collection\LanguageDetailCollection;
use Shopware\Api\Language\Event\Language\LanguageDeletedEvent;
use Shopware\Api\Language\Event\Language\LanguageWrittenEvent;
use Shopware\Api\Language\Repository\LanguageRepository;
use Shopware\Api\Language\Struct\LanguageBasicStruct;
use Shopware\Api\Language\Struct\LanguageDetailStruct;
use Shopware\System\Listing\Definition\ListingFacetTranslationDefinition;
use Shopware\System\Listing\Definition\ListingSortingTranslationDefinition;
use Shopware\System\Locale\Definition\LocaleDefinition;
use Shopware\System\Locale\Definition\LocaleTranslationDefinition;
use Shopware\System\Mail\Definition\MailTranslationDefinition;
use Shopware\Content\Media\Definition\MediaAlbumTranslationDefinition;
use Shopware\Content\Media\Definition\MediaTranslationDefinition;
use Shopware\Api\Order\Definition\OrderStateTranslationDefinition;
use Shopware\Api\Payment\Definition\PaymentMethodTranslationDefinition;
use Shopware\Content\Product\Definition\ProductManufacturerTranslationDefinition;
use Shopware\Content\Product\Definition\ProductTranslationDefinition;
use Shopware\Api\Shipping\Definition\ShippingMethodTranslationDefinition;
use Shopware\System\Tax\Definition\TaxAreaRuleTranslationDefinition;
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
            (new TranslationsAssociationField('categoryTranslations', CategoryTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('countryAreaTranslations', CountryAreaTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('countryStateTranslations', CountryStateTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('countryTranslations', CountryTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('currencyTranslations', CurrencyTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('customerGroupTranslations', CustomerGroupTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('listingFacetTranslations', ListingFacetTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('listingSortingTranslations', ListingSortingTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('localeTranslations', LocaleTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('mailTranslations', MailTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('mediaAlbumTranslations', MediaAlbumTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('mediaTranslations', MediaTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('orderStateTranslations', OrderStateTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('paymentMethodTranslations', PaymentMethodTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
            (new TranslationsAssociationField('productManufacturerTranslations', ProductManufacturerTranslationDefinition::class, 'language_id', false, 'id'))->setFlags(new WriteOnly(), new CascadeDelete()),
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

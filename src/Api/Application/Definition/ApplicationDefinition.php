<?php

namespace Shopware\Api\Application\Definition;

use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\JsonArrayField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Application\Repository\ApplicationRepository;
use Shopware\Api\Application\Collection\ApplicationBasicCollection;
use Shopware\Api\Application\Struct\ApplicationBasicStruct;
use Shopware\Api\Application\Event\Application\ApplicationWrittenEvent;
use Shopware\Api\Application\Event\Application\ApplicationDeletedEvent;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\WriteOnly;

use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Language\Definition\LanguageDefinition;
use Shopware\Api\Currency\Definition\CurrencyDefinition;
use Shopware\Api\Payment\Definition\PaymentMethodDefinition;
use Shopware\Api\Shipping\Definition\ShippingMethodDefinition;
use Shopware\Api\Country\Definition\CountryDefinition;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Customer\Definition\CustomerDefinition;
use Shopware\Api\Order\Definition\OrderDefinition;
use Shopware\Api\Product\Definition\ProductSeoCategoryDefinition;
use Shopware\Api\Seo\Definition\SeoUrlDefinition;
use Shopware\Api\Snippet\Definition\SnippetDefinition;

use Shopware\Api\Application\Collection\ApplicationDetailCollection;
use Shopware\Api\Application\Struct\ApplicationDetailStruct;            
            

class ApplicationDefinition extends EntityDefinition
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
        return 'application';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->setFlags(new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->setFlags(new Required()),
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),
            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->setFlags(new Required()),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new Required()),
            (new StringField('type', 'type'))->setFlags(new Required()),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new StringField('access_key', 'accessKey'))->setFlags(new Required()),
            (new StringField('secret_access_key', 'secretAccessKey'))->setFlags(new Required()),
            (new JsonArrayField('catalog_ids', 'catalogIds'))->setFlags(new Required()),
            (new JsonArrayField('currency_ids', 'currencyIds'))->setFlags(new Required()),
            (new JsonArrayField('language_ids', 'languageIds'))->setFlags(new Required()),
            new JsonArrayField('configuration', 'configuration'),
            new BoolField('active', 'active'),
            new StringField('tax_calculation_type', 'taxCalculationType'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, true),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, true),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, false),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, false),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, false),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ApplicationRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ApplicationBasicCollection::class;
    }

    public static function getDeletedEventClass(): string
    {
        return ApplicationDeletedEvent::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ApplicationWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ApplicationBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }


    public static function getDetailStructClass(): string
    {
        return ApplicationDetailStruct::class;
    }
    
    public static function getDetailCollectionClass(): string
    {
        return ApplicationDetailCollection::class;
    }

}
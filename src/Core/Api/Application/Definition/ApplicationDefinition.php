<?php declare(strict_types=1);

namespace Shopware\Api\Application\Definition;

use Shopware\Api\Application\Collection\ApplicationBasicCollection;
use Shopware\Api\Application\Collection\ApplicationDetailCollection;
use Shopware\Api\Application\Event\Application\ApplicationDeletedEvent;
use Shopware\Api\Application\Event\Application\ApplicationWrittenEvent;
use Shopware\Api\Application\Repository\ApplicationRepository;
use Shopware\Api\Application\Struct\ApplicationBasicStruct;
use Shopware\Api\Application\Struct\ApplicationDetailStruct;
use Shopware\System\Country\Definition\CountryDefinition;
use Shopware\System\Currency\Definition\CurrencyDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\JsonArrayField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Language\Definition\LanguageDefinition;
use Shopware\Api\Payment\Definition\PaymentMethodDefinition;
use Shopware\Api\Shipping\Definition\ShippingMethodDefinition;

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

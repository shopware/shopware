<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Definition;

use Shopware\Api\Category\Definition\CategoryDefinition;
use Shopware\Api\Country\Definition\CountryDefinition;
use Shopware\Api\Currency\Definition\CurrencyDefinition;
use Shopware\Api\Customer\Definition\CustomerGroupDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\UuidField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Locale\Definition\LocaleDefinition;
use Shopware\Api\Payment\Definition\PaymentMethodDefinition;
use Shopware\Api\Shipping\Definition\ShippingMethodDefinition;
use Shopware\Api\Shop\Collection\ShopBasicCollection;
use Shopware\Api\Shop\Collection\ShopDetailCollection;
use Shopware\Api\Shop\Event\Shop\ShopWrittenEvent;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Api\Shop\Struct\ShopDetailStruct;

class ShopDefinition extends EntityDefinition
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
        return 'shop';
    }

    public static function getFields(): FieldCollection
    {
        if (self::$fields) {
            return self::$fields;
        }

        self::$fields = new FieldCollection([
            (new UuidField('uuid', 'uuid'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('parent_uuid', 'parentUuid', self::class),
            (new FkField('shop_template_uuid', 'templateUuid', ShopTemplateDefinition::class))->setFlags(new Required()),
            (new FkField('document_template_uuid', 'documentTemplateUuid', ShopTemplateDefinition::class))->setFlags(new Required()),
            (new FkField('category_uuid', 'categoryUuid', CategoryDefinition::class))->setFlags(new Required()),
            (new FkField('locale_uuid', 'localeUuid', LocaleDefinition::class))->setFlags(new Required()),
            (new FkField('currency_uuid', 'currencyUuid', CurrencyDefinition::class))->setFlags(new Required()),
            (new FkField('customer_group_uuid', 'customerGroupUuid', CustomerGroupDefinition::class))->setFlags(new Required()),
            new FkField('fallback_translation_uuid', 'fallbackTranslationUuid', self::class),
            new FkField('payment_method_uuid', 'paymentMethodUuid', PaymentMethodDefinition::class),
            new FkField('shipping_method_uuid', 'shippingMethodUuid', ShippingMethodDefinition::class),
            new FkField('country_uuid', 'countryUuid', CountryDefinition::class),
            (new StringField('name', 'name'))->setFlags(new Required()),
            (new IntField('position', 'position'))->setFlags(new Required()),
            (new StringField('host', 'host'))->setFlags(new Required()),
            (new StringField('base_path', 'basePath'))->setFlags(new Required()),
            (new StringField('base_url', 'baseUrl'))->setFlags(new Required()),
            new StringField('title', 'title'),
            new LongTextField('hosts', 'hosts'),
            new BoolField('is_secure', 'isSecure'),
            new BoolField('customer_scope', 'customerScope'),
            new BoolField('is_default', 'isDefault'),
            new BoolField('active', 'active'),
            new StringField('tax_calculation_type', 'taxCalculationType'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
            new ManyToOneAssociationField('parent', 'parent_uuid', self::class, false),
            new ManyToOneAssociationField('template', 'shop_template_uuid', ShopTemplateDefinition::class, false),
            new ManyToOneAssociationField('documentTemplate', 'document_template_uuid', ShopTemplateDefinition::class, false),
            new ManyToOneAssociationField('category', 'category_uuid', CategoryDefinition::class, false),
            new ManyToOneAssociationField('locale', 'locale_uuid', LocaleDefinition::class, true),
            new ManyToOneAssociationField('currency', 'currency_uuid', CurrencyDefinition::class, true),
            new ManyToOneAssociationField('customerGroup', 'customer_group_uuid', CustomerGroupDefinition::class, false),
            new ManyToOneAssociationField('fallbackTranslation', 'fallback_translation_uuid', self::class, false),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_uuid', PaymentMethodDefinition::class, false),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_uuid', ShippingMethodDefinition::class, false),
            new ManyToOneAssociationField('country', 'country_uuid', CountryDefinition::class, false),
            new ManyToManyAssociationField('currencies', CurrencyDefinition::class, ShopCurrencyDefinition::class, false, 'shop_uuid', 'currency_uuid', 'currencyUuids'),
        ]);

        foreach (self::$extensions as $extension) {
            $extension->extendFields(self::$fields);
        }

        return self::$fields;
    }

    public static function getRepositoryClass(): string
    {
        return ShopRepository::class;
    }

    public static function getBasicCollectionClass(): string
    {
        return ShopBasicCollection::class;
    }

    public static function getWrittenEventClass(): string
    {
        return ShopWrittenEvent::class;
    }

    public static function getBasicStructClass(): string
    {
        return ShopBasicStruct::class;
    }

    public static function getTranslationDefinitionClass(): ?string
    {
        return null;
    }

    public static function getDetailStructClass(): string
    {
        return ShopDetailStruct::class;
    }

    public static function getDetailCollectionClass(): string
    {
        return ShopDetailCollection::class;
    }
}

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
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Locale\Definition\LocaleDefinition;
use Shopware\Api\Payment\Definition\PaymentMethodDefinition;
use Shopware\Api\Seo\Definition\SeoUrlDefinition;
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
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new FkField('parent_id', 'parentId', self::class),
            (new FkField('shop_template_id', 'templateId', ShopTemplateDefinition::class))->setFlags(new Required()),
            (new FkField('document_template_id', 'documentTemplateId', ShopTemplateDefinition::class))->setFlags(new Required()),
            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->setFlags(new Required()),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->setFlags(new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->setFlags(new Required()),
            (new FkField('customer_group_id', 'customerGroupId', CustomerGroupDefinition::class))->setFlags(new Required()),
            new FkField('fallback_translation_id', 'fallbackTranslationId', self::class),
            new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class),
            new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class),
            new FkField('country_id', 'countryId', CountryDefinition::class),
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
            new ManyToOneAssociationField('parent', 'parent_id', self::class, false),
            new ManyToOneAssociationField('template', 'shop_template_id', ShopTemplateDefinition::class, false),
            new ManyToOneAssociationField('documentTemplate', 'document_template_id', ShopTemplateDefinition::class, false),
            new ManyToOneAssociationField('category', 'category_id', CategoryDefinition::class, false),
            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, true),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, true),
            new ManyToOneAssociationField('customerGroup', 'customer_group_id', CustomerGroupDefinition::class, false),
            new ManyToOneAssociationField('fallbackTranslation', 'fallback_translation_id', self::class, false),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, false),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, false),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, false),
            new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'shop_id', false, 'id'),
            new ManyToManyAssociationField('currencies', CurrencyDefinition::class, ShopCurrencyDefinition::class, false, 'shop_id', 'currency_id', 'currencyIds'),
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

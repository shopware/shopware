<?php declare(strict_types=1);

namespace Shopware\Api\Shop\Definition;

use Shopware\Api\Category\Definition\CategoryDefinition;
use Shopware\Api\Config\Definition\ConfigFormFieldValueDefinition;
use Shopware\Api\Country\Definition\CountryDefinition;
use Shopware\Api\Currency\Definition\CurrencyDefinition;
use Shopware\Api\Customer\Definition\CustomerDefinition;
use Shopware\Api\Customer\Definition\CustomerGroupDefinition;
use Shopware\Api\Entity\EntityDefinition;
use Shopware\Api\Entity\EntityExtensionInterface;
use Shopware\Api\Entity\Field\BoolField;
use Shopware\Api\Entity\Field\DateField;
use Shopware\Api\Entity\Field\FkField;
use Shopware\Api\Entity\Field\IdField;
use Shopware\Api\Entity\Field\IntField;
use Shopware\Api\Entity\Field\JsonArrayField;
use Shopware\Api\Entity\Field\LongTextField;
use Shopware\Api\Entity\Field\ManyToManyAssociationField;
use Shopware\Api\Entity\Field\ManyToOneAssociationField;
use Shopware\Api\Entity\Field\OneToManyAssociationField;
use Shopware\Api\Entity\Field\ReferenceVersionField;
use Shopware\Api\Entity\Field\StringField;
use Shopware\Api\Entity\Field\TenantIdField;
use Shopware\Api\Entity\Field\VersionField;
use Shopware\Api\Entity\FieldCollection;
use Shopware\Api\Entity\Write\Flag\CascadeDelete;
use Shopware\Api\Entity\Write\Flag\PrimaryKey;
use Shopware\Api\Entity\Write\Flag\Required;
use Shopware\Api\Entity\Write\Flag\RestrictDelete;
use Shopware\Api\Entity\Write\Flag\SearchRanking;
use Shopware\Api\Entity\Write\Flag\WriteOnly;
use Shopware\Api\Locale\Definition\LocaleDefinition;
use Shopware\Api\Mail\Definition\MailAttachmentDefinition;
use Shopware\Api\Order\Definition\OrderDefinition;
use Shopware\Api\Payment\Definition\PaymentMethodDefinition;
use Shopware\Api\Product\Definition\ProductSeoCategoryDefinition;
use Shopware\Api\Shipping\Definition\ShippingMethodDefinition;
use Shopware\Api\Shop\Collection\ShopBasicCollection;
use Shopware\Api\Shop\Collection\ShopDetailCollection;
use Shopware\Api\Shop\Event\Shop\ShopDeletedEvent;
use Shopware\Api\Shop\Event\Shop\ShopWrittenEvent;
use Shopware\Api\Shop\Repository\ShopRepository;
use Shopware\Api\Shop\Struct\ShopBasicStruct;
use Shopware\Api\Shop\Struct\ShopDetailStruct;
use Shopware\Api\Snippet\Definition\SnippetDefinition;

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
            new TenantIdField(),
            (new IdField('id', 'id'))->setFlags(new PrimaryKey(), new Required()),
            new VersionField(),

            (new JsonArrayField('catalog_ids', 'catalogIds'))->setFlags(new Required()),

            (new FkField('shop_template_id', 'templateId', ShopTemplateDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ShopTemplateDefinition::class))->setFlags(new Required()),

            (new FkField('document_template_id', 'documentTemplateId', ShopTemplateDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ShopTemplateDefinition::class, 'document_template_version_id'))->setFlags(new Required()),

            (new FkField('category_id', 'categoryId', CategoryDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CategoryDefinition::class))->setFlags(new Required()),

            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(LocaleDefinition::class))->setFlags(new Required()),

            (new FkField('customer_group_id', 'customerGroupId', CustomerGroupDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CustomerGroupDefinition::class))->setFlags(new Required()),

            new FkField('fallback_translation_id', 'fallbackTranslationId', self::class),
            new ReferenceVersionField(self::class, 'fallback_translation_version_id'),

            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(PaymentMethodDefinition::class))->setFlags(new Required()),

            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(ShippingMethodDefinition::class))->setFlags(new Required()),

            (new FkField('country_id', 'countryId', CountryDefinition::class))->setFlags(new Required()),
            (new ReferenceVersionField(CountryDefinition::class))->setFlags(new Required()),

            (new StringField('name', 'name'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new IntField('position', 'position'))->setFlags(new Required()),
            (new StringField('host', 'host'))->setFlags(new Required(), new SearchRanking(self::HIGH_SEARCH_RANKING)),
            (new StringField('base_path', 'basePath'))->setFlags(new Required(), new SearchRanking(self::MIDDLE_SEARCH_RANKING)),
            (new StringField('base_url', 'baseUrl'))->setFlags(new Required()),
            (new StringField('title', 'title'))->setFlags(new SearchRanking(self::HIGH_SEARCH_RANKING)),
            new LongTextField('hosts', 'hosts'),
            new BoolField('is_secure', 'isSecure'),
            new BoolField('customer_scope', 'customerScope'),
            new BoolField('is_default', 'isDefault'),
            new BoolField('active', 'active'),
            new StringField('tax_calculation_type', 'taxCalculationType'),
            new DateField('created_at', 'createdAt'),
            new DateField('updated_at', 'updatedAt'),
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
            (new OneToManyAssociationField('configFormFieldValues', ConfigFormFieldValueDefinition::class, 'shop_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'shop_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('mailAttachments', MailAttachmentDefinition::class, 'shop_id', false, 'id'))->setFlags(new WriteOnly()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'shop_id', false, 'id'))->setFlags(new RestrictDelete(), new WriteOnly()),
            (new OneToManyAssociationField('templateConfigFormFieldValues', ShopTemplateConfigFormFieldValueDefinition::class, 'shop_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new OneToManyAssociationField('snippets', SnippetDefinition::class, 'shop_id', false, 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
            (new ManyToManyAssociationField('productSeoCategories', CategoryDefinition::class, ProductSeoCategoryDefinition::class, false, 'shop_id', 'id'))->setFlags(new CascadeDelete(), new WriteOnly()),
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

    public static function getDeletedEventClass(): string
    {
        return ShopDeletedEvent::class;
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

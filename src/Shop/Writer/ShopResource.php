<?php declare(strict_types=1);

namespace Shopware\Shop\Writer;

use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\DateField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\LongTextWithHtmlField;
use Shopware\Framework\Write\Field\FloatField;
use Shopware\Framework\Write\Field\TranslatedField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Resource;

class ShopResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const MAIN_ID_FIELD = 'mainId';
    protected const NAME_FIELD = 'name';
    protected const TITLE_FIELD = 'title';
    protected const POSITION_FIELD = 'position';
    protected const HOST_FIELD = 'host';
    protected const BASE_PATH_FIELD = 'basePath';
    protected const BASE_URL_FIELD = 'baseUrl';
    protected const HOSTS_FIELD = 'hosts';
    protected const SECURE_FIELD = 'secure';
    protected const TEMPLATE_ID_FIELD = 'templateId';
    protected const DOCUMENT_TEMPLATE_ID_FIELD = 'documentTemplateId';
    protected const CATEGORY_ID_FIELD = 'categoryId';
    protected const LOCALE_ID_FIELD = 'localeId';
    protected const CURRENCY_ID_FIELD = 'currencyId';
    protected const CUSTOMER_GROUP_ID_FIELD = 'customerGroupId';
    protected const FALLBACK_ID_FIELD = 'fallbackId';
    protected const CUSTOMER_SCOPE_FIELD = 'customerScope';
    protected const IS_DEFAULT_FIELD = 'isDefault';
    protected const ACTIVE_FIELD = 'active';
    protected const PAYMENT_METHOD_ID_FIELD = 'paymentMethodId';
    protected const SHIPPING_METHOD_ID_FIELD = 'shippingMethodId';
    protected const AREA_COUNTRY_ID_FIELD = 'areaCountryId';
    protected const TAX_CALCULATION_TYPE_FIELD = 'taxCalculationType';

    public function __construct()
    {
        parent::__construct('shop');
        
        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::MAIN_ID_FIELD] = new IntField('main_id');
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::TITLE_FIELD] = new StringField('title');
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::HOST_FIELD] = new StringField('host');
        $this->fields[self::BASE_PATH_FIELD] = new StringField('base_path');
        $this->fields[self::BASE_URL_FIELD] = new StringField('base_url');
        $this->fields[self::HOSTS_FIELD] = (new LongTextField('hosts'))->setFlags(new Required());
        $this->fields[self::SECURE_FIELD] = (new BoolField('secure'))->setFlags(new Required());
        $this->fields[self::TEMPLATE_ID_FIELD] = new IntField('shop_template_id');
        $this->fields[self::DOCUMENT_TEMPLATE_ID_FIELD] = new IntField('document_template_id');
        $this->fields[self::CATEGORY_ID_FIELD] = new IntField('category_id');
        $this->fields[self::LOCALE_ID_FIELD] = new IntField('locale_id');
        $this->fields[self::CURRENCY_ID_FIELD] = new IntField('currency_id');
        $this->fields[self::CUSTOMER_GROUP_ID_FIELD] = new IntField('customer_group_id');
        $this->fields[self::FALLBACK_ID_FIELD] = new IntField('fallback_id');
        $this->fields[self::CUSTOMER_SCOPE_FIELD] = (new BoolField('customer_scope'))->setFlags(new Required());
        $this->fields[self::IS_DEFAULT_FIELD] = (new BoolField('is_default'))->setFlags(new Required());
        $this->fields[self::ACTIVE_FIELD] = (new BoolField('active'))->setFlags(new Required());
        $this->fields[self::PAYMENT_METHOD_ID_FIELD] = (new IntField('payment_method_id'))->setFlags(new Required());
        $this->fields[self::SHIPPING_METHOD_ID_FIELD] = (new IntField('shipping_method_id'))->setFlags(new Required());
        $this->fields[self::AREA_COUNTRY_ID_FIELD] = (new IntField('area_country_id'))->setFlags(new Required());
        $this->fields[self::TAX_CALCULATION_TYPE_FIELD] = new StringField('tax_calculation_type');
        $this->fields['configFormFieldValues'] = new SubresourceField(\Shopware\Framework\Write\Resource\ConfigFormFieldValueResource::class);
        $this->fields['customers'] = new SubresourceField(\Shopware\Customer\Writer\CustomerResource::class);
        $this->fields['mailAttachments'] = new SubresourceField(\Shopware\Framework\Write\Resource\MailAttachmentResource::class);
        $this->fields['paymentMethodShops'] = new SubresourceField(\Shopware\PaymentMethod\Writer\PaymentMethodShopResource::class);
        $this->fields['premiumProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\PremiumProductResource::class);
        $this->fields['productCategorySeos'] = new SubresourceField(\Shopware\Product\Writer\ProductCategorySeoResource::class);
        $this->fields['productVotes'] = new SubresourceField(\Shopware\Product\Writer\ProductVoteResource::class);
        $this->fields['shippingMethods'] = new SubresourceField(\Shopware\ShippingMethod\Writer\ShippingMethodResource::class);
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', \Shopware\Shop\Writer\ShopResource::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', \Shopware\Shop\Writer\ShopResource::class, 'uuid'));
        $this->fields['template'] = new ReferenceField('templateUuid', 'uuid', \Shopware\ShopTemplate\Writer\ShopTemplateResource::class);
        $this->fields['templateUuid'] = (new FkField('shop_template_uuid', \Shopware\ShopTemplate\Writer\ShopTemplateResource::class, 'uuid'));
        $this->fields['documentTemplate'] = new ReferenceField('documentTemplateUuid', 'uuid', \Shopware\ShopTemplate\Writer\ShopTemplateResource::class);
        $this->fields['documentTemplateUuid'] = (new FkField('document_template_uuid', \Shopware\ShopTemplate\Writer\ShopTemplateResource::class, 'uuid'));
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', \Shopware\Category\Writer\CategoryResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', \Shopware\Category\Writer\CategoryResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['locale'] = new ReferenceField('localeUuid', 'uuid', \Shopware\Locale\Writer\LocaleResource::class);
        $this->fields['localeUuid'] = (new FkField('locale_uuid', \Shopware\Locale\Writer\LocaleResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['currency'] = new ReferenceField('currencyUuid', 'uuid', \Shopware\Currency\Writer\CurrencyResource::class);
        $this->fields['currencyUuid'] = (new FkField('currency_uuid', \Shopware\Currency\Writer\CurrencyResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\CustomerGroupResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\CustomerGroupResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['fallbackLocale'] = new ReferenceField('fallbackLocaleUuid', 'uuid', \Shopware\Locale\Writer\LocaleResource::class);
        $this->fields['fallbackLocaleUuid'] = (new FkField('fallback_locale_uuid', \Shopware\Locale\Writer\LocaleResource::class, 'uuid'));
        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', \Shopware\PaymentMethod\Writer\PaymentMethodResource::class);
        $this->fields['paymentMethodUuid'] = (new FkField('payment_method_uuid', \Shopware\PaymentMethod\Writer\PaymentMethodResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', \Shopware\ShippingMethod\Writer\ShippingMethodResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', \Shopware\ShippingMethod\Writer\ShippingMethodResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Writer\AreaCountryResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', \Shopware\AreaCountry\Writer\AreaCountryResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['s'] = new SubresourceField(\Shopware\Shop\Writer\ShopResource::class);
        $this->fields['currencys'] = new SubresourceField(\Shopware\Shop\Writer\ShopCurrencyResource::class);
        $this->fields['pageGroupMappings'] = new SubresourceField(\Shopware\Shop\Writer\ShopPageGroupMappingResource::class);
        $this->fields['templateConfigFormFieldValues'] = new SubresourceField(\Shopware\ShopTemplate\Writer\ShopTemplateConfigFormFieldValueResource::class);
        $this->fields['snippets'] = new SubresourceField(\Shopware\Framework\Write\Resource\SnippetResource::class);
        $this->fields['statisticProductImpressions'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticProductImpressionResource::class);
        $this->fields['statisticSearchs'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticSearchResource::class);
        $this->fields['statisticVisitors'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticVisitorResource::class);
    }
    
    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\ConfigFormFieldValueResource::class,
            \Shopware\Customer\Writer\CustomerResource::class,
            \Shopware\Framework\Write\Resource\MailAttachmentResource::class,
            \Shopware\PaymentMethod\Writer\PaymentMethodShopResource::class,
            \Shopware\Framework\Write\Resource\PremiumProductResource::class,
            \Shopware\Product\Writer\ProductCategorySeoResource::class,
            \Shopware\Product\Writer\ProductVoteResource::class,
            \Shopware\ShippingMethod\Writer\ShippingMethodResource::class,
            \Shopware\Shop\Writer\ShopResource::class,
            \Shopware\ShopTemplate\Writer\ShopTemplateResource::class,
            \Shopware\Category\Writer\CategoryResource::class,
            \Shopware\Locale\Writer\LocaleResource::class,
            \Shopware\Currency\Writer\CurrencyResource::class,
            \Shopware\CustomerGroup\Writer\CustomerGroupResource::class,
            \Shopware\PaymentMethod\Writer\PaymentMethodResource::class,
            \Shopware\AreaCountry\Writer\AreaCountryResource::class,
            \Shopware\Shop\Writer\ShopCurrencyResource::class,
            \Shopware\Shop\Writer\ShopPageGroupMappingResource::class,
            \Shopware\ShopTemplate\Writer\ShopTemplateConfigFormFieldValueResource::class,
            \Shopware\Framework\Write\Resource\SnippetResource::class,
            \Shopware\Framework\Write\Resource\StatisticProductImpressionResource::class,
            \Shopware\Framework\Write\Resource\StatisticSearchResource::class,
            \Shopware\Framework\Write\Resource\StatisticVisitorResource::class
        ];
    }
}

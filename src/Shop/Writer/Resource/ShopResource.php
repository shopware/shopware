<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\Resource;

class ShopResource extends Resource
{
    protected const UUID_FIELD = 'uuid';
    protected const NAME_FIELD = 'name';
    protected const TITLE_FIELD = 'title';
    protected const POSITION_FIELD = 'position';
    protected const HOST_FIELD = 'host';
    protected const BASE_PATH_FIELD = 'basePath';
    protected const BASE_URL_FIELD = 'baseUrl';
    protected const HOSTS_FIELD = 'hosts';
    protected const IS_SECURE_FIELD = 'isSecure';
    protected const CUSTOMER_SCOPE_FIELD = 'customerScope';
    protected const IS_DEFAULT_FIELD = 'isDefault';
    protected const ACTIVE_FIELD = 'active';
    protected const TAX_CALCULATION_TYPE_FIELD = 'taxCalculationType';

    public function __construct()
    {
        parent::__construct('shop');

        $this->primaryKeyFields[self::UUID_FIELD] = (new UuidField('uuid'))->setFlags(new Required());
        $this->fields[self::NAME_FIELD] = (new StringField('name'))->setFlags(new Required());
        $this->fields[self::TITLE_FIELD] = new StringField('title');
        $this->fields[self::POSITION_FIELD] = (new IntField('position'))->setFlags(new Required());
        $this->fields[self::HOST_FIELD] = (new StringField('host'))->setFlags(new Required());
        $this->fields[self::BASE_PATH_FIELD] = (new StringField('base_path'))->setFlags(new Required());
        $this->fields[self::BASE_URL_FIELD] = (new StringField('base_url'))->setFlags(new Required());
        $this->fields[self::HOSTS_FIELD] = new LongTextField('hosts');
        $this->fields[self::IS_SECURE_FIELD] = new BoolField('is_secure');
        $this->fields[self::CUSTOMER_SCOPE_FIELD] = new BoolField('customer_scope');
        $this->fields[self::IS_DEFAULT_FIELD] = new BoolField('is_default');
        $this->fields[self::ACTIVE_FIELD] = new BoolField('active');
        $this->fields[self::TAX_CALCULATION_TYPE_FIELD] = new StringField('tax_calculation_type');
        $this->fields['configFormFieldValues'] = new SubresourceField(\Shopware\Framework\Write\Resource\ConfigFormFieldValueResource::class);
        $this->fields['customers'] = new SubresourceField(\Shopware\Customer\Writer\Resource\CustomerResource::class);
        $this->fields['mailAttachments'] = new SubresourceField(\Shopware\Framework\Write\Resource\MailAttachmentResource::class);
        $this->fields['orders'] = new SubresourceField(\Shopware\Order\Writer\Resource\OrderResource::class);
        $this->fields['paymentMethodShops'] = new SubresourceField(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopResource::class);
        $this->fields['premiumProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\PremiumProductResource::class);
        $this->fields['productCategorySeos'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductCategorySeoResource::class);
        $this->fields['productVotes'] = new SubresourceField(\Shopware\ProductVote\Writer\Resource\ProductVoteResource::class);
        $this->fields['shippingMethods'] = new SubresourceField(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class);
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['parentUuid'] = new FkField('parent_uuid', \Shopware\Shop\Writer\Resource\ShopResource::class, 'uuid');
        $this->fields['template'] = new ReferenceField('templateUuid', 'uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class);
        $this->fields['templateUuid'] = (new FkField('shop_template_uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['documentTemplate'] = new ReferenceField('documentTemplateUuid', 'uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class);
        $this->fields['documentTemplateUuid'] = (new FkField('document_template_uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', \Shopware\Category\Writer\Resource\CategoryResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', \Shopware\Category\Writer\Resource\CategoryResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['locale'] = new ReferenceField('localeUuid', 'uuid', \Shopware\Locale\Writer\Resource\LocaleResource::class);
        $this->fields['localeUuid'] = (new FkField('locale_uuid', \Shopware\Locale\Writer\Resource\LocaleResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['currency'] = new ReferenceField('currencyUuid', 'uuid', \Shopware\Currency\Writer\Resource\CurrencyResource::class);
        $this->fields['currencyUuid'] = (new FkField('currency_uuid', \Shopware\Currency\Writer\Resource\CurrencyResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['fallbackLocale'] = new ReferenceField('fallbackLocaleUuid', 'uuid', \Shopware\Locale\Writer\Resource\LocaleResource::class);
        $this->fields['fallbackLocaleUuid'] = new FkField('fallback_locale_uuid', \Shopware\Locale\Writer\Resource\LocaleResource::class, 'uuid');
        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class);
        $this->fields['paymentMethodUuid'] = new FkField('payment_method_uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class, 'uuid');
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class);
        $this->fields['shippingMethodUuid'] = new FkField('shipping_method_uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class, 'uuid');
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class);
        $this->fields['areaCountryUuid'] = new FkField('area_country_uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class, 'uuid');
        $this->fields['parent'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopResource::class);
        $this->fields['currencies'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopCurrencyResource::class);
        $this->fields['pageGroupMappings'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::class);
        $this->fields['templateConfigFormFieldValues'] = new SubresourceField(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueResource::class);
        $this->fields['snippets'] = new SubresourceField(\Shopware\Framework\Write\Resource\SnippetResource::class);
        $this->fields['statisticProductImpressions'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticProductImpressionResource::class);
        $this->fields['statisticSearchs'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticSearchResource::class);
        $this->fields['statisticVisitors'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticVisitorResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\ConfigFormFieldValueResource::class,
            \Shopware\Customer\Writer\Resource\CustomerResource::class,
            \Shopware\Framework\Write\Resource\MailAttachmentResource::class,
            \Shopware\Order\Writer\Resource\OrderResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopResource::class,
            \Shopware\Framework\Write\Resource\PremiumProductResource::class,
            \Shopware\Product\Writer\Resource\ProductCategorySeoResource::class,
            \Shopware\ProductVote\Writer\Resource\ProductVoteResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class,
            \Shopware\Shop\Writer\Resource\ShopResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class,
            \Shopware\Category\Writer\Resource\CategoryResource::class,
            \Shopware\Locale\Writer\Resource\LocaleResource::class,
            \Shopware\Currency\Writer\Resource\CurrencyResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class,
            \Shopware\Shop\Writer\Resource\ShopCurrencyResource::class,
            \Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueResource::class,
            \Shopware\Framework\Write\Resource\SnippetResource::class,
            \Shopware\Framework\Write\Resource\StatisticProductImpressionResource::class,
            \Shopware\Framework\Write\Resource\StatisticSearchResource::class,
            \Shopware\Framework\Write\Resource\StatisticVisitorResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, array $errors = []): \Shopware\Shop\Event\ShopWrittenEvent
    {
        $event = new \Shopware\Shop\Event\ShopWrittenEvent($updates[self::class] ?? [], $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\ConfigFormFieldValueResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ConfigFormFieldValueResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Customer\Writer\Resource\CustomerResource::class])) {
            $event->addEvent(\Shopware\Customer\Writer\Resource\CustomerResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Customer\Writer\Resource\CustomerResource::class])) {
            $event->addEvent(\Shopware\Customer\Writer\Resource\CustomerResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\MailAttachmentResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MailAttachmentResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Order\Writer\Resource\OrderResource::class])) {
            $event->addEvent(\Shopware\Order\Writer\Resource\OrderResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\PremiumProductResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\PremiumProductResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductCategorySeoResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductCategorySeoResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ProductVote\Writer\Resource\ProductVoteResource::class])) {
            $event->addEvent(\Shopware\ProductVote\Writer\Resource\ProductVoteResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class])) {
            $event->addEvent(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::class])) {
            $event->addEvent(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Locale\Writer\Resource\LocaleResource::class])) {
            $event->addEvent(\Shopware\Locale\Writer\Resource\LocaleResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Currency\Writer\Resource\CurrencyResource::class])) {
            $event->addEvent(\Shopware\Currency\Writer\Resource\CurrencyResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Locale\Writer\Resource\LocaleResource::class])) {
            $event->addEvent(\Shopware\Locale\Writer\Resource\LocaleResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopCurrencyResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopCurrencyResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopPageGroupMappingResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueResource::class])) {
            $event->addEvent(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\SnippetResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\SnippetResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\StatisticProductImpressionResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\StatisticProductImpressionResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\StatisticSearchResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\StatisticSearchResource::createWrittenEvent($updates));
        }

        if (!empty($updates[\Shopware\Framework\Write\Resource\StatisticVisitorResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\StatisticVisitorResource::createWrittenEvent($updates));
        }

        return $event;
    }
}

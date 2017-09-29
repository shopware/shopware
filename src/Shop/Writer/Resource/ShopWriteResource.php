<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Write\Field\BoolField;
use Shopware\Framework\Write\Field\FkField;
use Shopware\Framework\Write\Field\IntField;
use Shopware\Framework\Write\Field\LongTextField;
use Shopware\Framework\Write\Field\ReferenceField;
use Shopware\Framework\Write\Field\StringField;
use Shopware\Framework\Write\Field\SubresourceField;
use Shopware\Framework\Write\Field\UuidField;
use Shopware\Framework\Write\Flag\Required;
use Shopware\Framework\Write\WriteResource;

class ShopWriteResource extends WriteResource
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
        $this->fields['configFormFieldValues'] = new SubresourceField(\Shopware\Framework\Write\Resource\ConfigFormFieldValueWriteResource::class);
        $this->fields['customers'] = new SubresourceField(\Shopware\Customer\Writer\Resource\CustomerWriteResource::class);
        $this->fields['mailAttachments'] = new SubresourceField(\Shopware\Framework\Write\Resource\MailAttachmentWriteResource::class);
        $this->fields['orders'] = new SubresourceField(\Shopware\Order\Writer\Resource\OrderWriteResource::class);
        $this->fields['paymentMethodShops'] = new SubresourceField(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopWriteResource::class);
        $this->fields['premiumProducts'] = new SubresourceField(\Shopware\Framework\Write\Resource\PremiumProductWriteResource::class);
        $this->fields['productCategorySeos'] = new SubresourceField(\Shopware\Product\Writer\Resource\ProductCategorySeoWriteResource::class);
        $this->fields['productVotes'] = new SubresourceField(\Shopware\ProductVote\Writer\Resource\ProductVoteWriteResource::class);
        $this->fields['shippingMethods'] = new SubresourceField(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class);
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', \Shopware\Shop\Writer\Resource\ShopWriteResource::class, 'uuid'));
        $this->fields['template'] = new ReferenceField('templateUuid', 'uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateWriteResource::class);
        $this->fields['templateUuid'] = (new FkField('shop_template_uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['documentTemplate'] = new ReferenceField('documentTemplateUuid', 'uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateWriteResource::class);
        $this->fields['documentTemplateUuid'] = (new FkField('document_template_uuid', \Shopware\ShopTemplate\Writer\Resource\ShopTemplateWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', \Shopware\Category\Writer\Resource\CategoryWriteResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', \Shopware\Category\Writer\Resource\CategoryWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['locale'] = new ReferenceField('localeUuid', 'uuid', \Shopware\Locale\Writer\Resource\LocaleWriteResource::class);
        $this->fields['localeUuid'] = (new FkField('locale_uuid', \Shopware\Locale\Writer\Resource\LocaleWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['currency'] = new ReferenceField('currencyUuid', 'uuid', \Shopware\Currency\Writer\Resource\CurrencyWriteResource::class);
        $this->fields['currencyUuid'] = (new FkField('currency_uuid', \Shopware\Currency\Writer\Resource\CurrencyWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['fallbackLocale'] = new ReferenceField('fallbackLocaleUuid', 'uuid', \Shopware\Locale\Writer\Resource\LocaleWriteResource::class);
        $this->fields['fallbackLocaleUuid'] = (new FkField('fallback_locale_uuid', \Shopware\Locale\Writer\Resource\LocaleWriteResource::class, 'uuid'));
        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class);
        $this->fields['paymentMethodUuid'] = (new FkField('payment_method_uuid', \Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class, 'uuid'));
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class, 'uuid'));
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class, 'uuid'));
        $this->fields['parent'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopWriteResource::class);
        $this->fields['currencies'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopCurrencyWriteResource::class);
        $this->fields['pageGroupMappings'] = new SubresourceField(\Shopware\Shop\Writer\Resource\ShopPageGroupMappingWriteResource::class);
        $this->fields['templateConfigFormFieldValues'] = new SubresourceField(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueWriteResource::class);
        $this->fields['snippets'] = new SubresourceField(\Shopware\Framework\Write\Resource\SnippetWriteResource::class);
        $this->fields['statisticProductImpressions'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticProductImpressionWriteResource::class);
        $this->fields['statisticSearchs'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticSearchWriteResource::class);
        $this->fields['statisticVisitors'] = new SubresourceField(\Shopware\Framework\Write\Resource\StatisticVisitorWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            \Shopware\Framework\Write\Resource\ConfigFormFieldValueWriteResource::class,
            \Shopware\Customer\Writer\Resource\CustomerWriteResource::class,
            \Shopware\Framework\Write\Resource\MailAttachmentWriteResource::class,
            \Shopware\Order\Writer\Resource\OrderWriteResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopWriteResource::class,
            \Shopware\Framework\Write\Resource\PremiumProductWriteResource::class,
            \Shopware\Product\Writer\Resource\ProductCategorySeoWriteResource::class,
            \Shopware\ProductVote\Writer\Resource\ProductVoteWriteResource::class,
            \Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopWriteResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateWriteResource::class,
            \Shopware\Category\Writer\Resource\CategoryWriteResource::class,
            \Shopware\Locale\Writer\Resource\LocaleWriteResource::class,
            \Shopware\Currency\Writer\Resource\CurrencyWriteResource::class,
            \Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class,
            \Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class,
            \Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopCurrencyWriteResource::class,
            \Shopware\Shop\Writer\Resource\ShopPageGroupMappingWriteResource::class,
            \Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueWriteResource::class,
            \Shopware\Framework\Write\Resource\SnippetWriteResource::class,
            \Shopware\Framework\Write\Resource\StatisticProductImpressionWriteResource::class,
            \Shopware\Framework\Write\Resource\StatisticSearchWriteResource::class,
            \Shopware\Framework\Write\Resource\StatisticVisitorWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $errors = []): \Shopware\Shop\Event\ShopWrittenEvent
    {
        $event = new \Shopware\Shop\Event\ShopWrittenEvent($updates[self::class] ?? [], $context, $errors);

        unset($updates[self::class]);

        if (!empty($updates[\Shopware\Framework\Write\Resource\ConfigFormFieldValueWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\ConfigFormFieldValueWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Customer\Writer\Resource\CustomerWriteResource::class])) {
            $event->addEvent(\Shopware\Customer\Writer\Resource\CustomerWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\MailAttachmentWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\MailAttachmentWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Order\Writer\Resource\OrderWriteResource::class])) {
            $event->addEvent(\Shopware\Order\Writer\Resource\OrderWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopWriteResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\PremiumProductWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\PremiumProductWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Product\Writer\Resource\ProductCategorySeoWriteResource::class])) {
            $event->addEvent(\Shopware\Product\Writer\Resource\ProductCategorySeoWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ProductVote\Writer\Resource\ProductVoteWriteResource::class])) {
            $event->addEvent(\Shopware\ProductVote\Writer\Resource\ProductVoteWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::class])) {
            $event->addEvent(\Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ShopTemplate\Writer\Resource\ShopTemplateWriteResource::class])) {
            $event->addEvent(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Category\Writer\Resource\CategoryWriteResource::class])) {
            $event->addEvent(\Shopware\Category\Writer\Resource\CategoryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Locale\Writer\Resource\LocaleWriteResource::class])) {
            $event->addEvent(\Shopware\Locale\Writer\Resource\LocaleWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Currency\Writer\Resource\CurrencyWriteResource::class])) {
            $event->addEvent(\Shopware\Currency\Writer\Resource\CurrencyWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::class])) {
            $event->addEvent(\Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::class])) {
            $event->addEvent(\Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::class])) {
            $event->addEvent(\Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopCurrencyWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopCurrencyWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Shop\Writer\Resource\ShopPageGroupMappingWriteResource::class])) {
            $event->addEvent(\Shopware\Shop\Writer\Resource\ShopPageGroupMappingWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueWriteResource::class])) {
            $event->addEvent(\Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\SnippetWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\SnippetWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\StatisticProductImpressionWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\StatisticProductImpressionWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\StatisticSearchWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\StatisticSearchWriteResource::createWrittenEvent($updates, $context));
        }
        if (!empty($updates[\Shopware\Framework\Write\Resource\StatisticVisitorWriteResource::class])) {
            $event->addEvent(\Shopware\Framework\Write\Resource\StatisticVisitorWriteResource::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Shop\Writer\Resource;

use Shopware\AreaCountry\Writer\Resource\AreaCountryWriteResource;
use Shopware\Category\Writer\Resource\CategoryWriteResource;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Writer\Resource\CurrencyWriteResource;
use Shopware\Customer\Writer\Resource\CustomerWriteResource;
use Shopware\CustomerGroup\Writer\Resource\CustomerGroupWriteResource;
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
use Shopware\Framework\Writer\Resource\ConfigFormFieldValueWriteResource;
use Shopware\Framework\Writer\Resource\MailAttachmentWriteResource;
use Shopware\Framework\Writer\Resource\PremiumProductWriteResource;
use Shopware\Framework\Writer\Resource\SnippetWriteResource;
use Shopware\Framework\Writer\Resource\StatisticProductImpressionWriteResource;
use Shopware\Framework\Writer\Resource\StatisticSearchWriteResource;
use Shopware\Framework\Writer\Resource\StatisticVisitorWriteResource;
use Shopware\Locale\Writer\Resource\LocaleWriteResource;
use Shopware\Order\Writer\Resource\OrderWriteResource;
use Shopware\PaymentMethod\Writer\Resource\PaymentMethodShopWriteResource;
use Shopware\PaymentMethod\Writer\Resource\PaymentMethodWriteResource;
use Shopware\Product\Writer\Resource\ProductCategorySeoWriteResource;
use Shopware\ProductVote\Writer\Resource\ProductVoteWriteResource;
use Shopware\ShippingMethod\Writer\Resource\ShippingMethodWriteResource;
use Shopware\Shop\Event\ShopWrittenEvent;
use Shopware\ShopTemplate\Writer\Resource\ShopTemplateConfigFormFieldValueWriteResource;
use Shopware\ShopTemplate\Writer\Resource\ShopTemplateWriteResource;

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
        $this->fields['configFormFieldValues'] = new SubresourceField(ConfigFormFieldValueWriteResource::class);
        $this->fields['customers'] = new SubresourceField(CustomerWriteResource::class);
        $this->fields['mailAttachments'] = new SubresourceField(MailAttachmentWriteResource::class);
        $this->fields['orders'] = new SubresourceField(OrderWriteResource::class);
        $this->fields['paymentMethodShops'] = new SubresourceField(PaymentMethodShopWriteResource::class);
        $this->fields['premiumProducts'] = new SubresourceField(PremiumProductWriteResource::class);
        $this->fields['productCategorySeos'] = new SubresourceField(ProductCategorySeoWriteResource::class);
        $this->fields['productVotes'] = new SubresourceField(ProductVoteWriteResource::class);
        $this->fields['shippingMethods'] = new SubresourceField(ShippingMethodWriteResource::class);
        $this->fields['parent'] = new ReferenceField('parentUuid', 'uuid', self::class);
        $this->fields['parentUuid'] = (new FkField('parent_uuid', self::class, 'uuid'));
        $this->fields['template'] = new ReferenceField('templateUuid', 'uuid', ShopTemplateWriteResource::class);
        $this->fields['templateUuid'] = (new FkField('shop_template_uuid', ShopTemplateWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['documentTemplate'] = new ReferenceField('documentTemplateUuid', 'uuid', ShopTemplateWriteResource::class);
        $this->fields['documentTemplateUuid'] = (new FkField('document_template_uuid', ShopTemplateWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['category'] = new ReferenceField('categoryUuid', 'uuid', CategoryWriteResource::class);
        $this->fields['categoryUuid'] = (new FkField('category_uuid', CategoryWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['locale'] = new ReferenceField('localeUuid', 'uuid', LocaleWriteResource::class);
        $this->fields['localeUuid'] = (new FkField('locale_uuid', LocaleWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['currency'] = new ReferenceField('currencyUuid', 'uuid', CurrencyWriteResource::class);
        $this->fields['currencyUuid'] = (new FkField('currency_uuid', CurrencyWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['customerGroup'] = new ReferenceField('customerGroupUuid', 'uuid', CustomerGroupWriteResource::class);
        $this->fields['customerGroupUuid'] = (new FkField('customer_group_uuid', CustomerGroupWriteResource::class, 'uuid'))->setFlags(new Required());
        $this->fields['fallbackLocale'] = new ReferenceField('fallbackLocaleUuid', 'uuid', LocaleWriteResource::class);
        $this->fields['fallbackLocaleUuid'] = (new FkField('fallback_locale_uuid', LocaleWriteResource::class, 'uuid'));
        $this->fields['paymentMethod'] = new ReferenceField('paymentMethodUuid', 'uuid', PaymentMethodWriteResource::class);
        $this->fields['paymentMethodUuid'] = (new FkField('payment_method_uuid', PaymentMethodWriteResource::class, 'uuid'));
        $this->fields['shippingMethod'] = new ReferenceField('shippingMethodUuid', 'uuid', ShippingMethodWriteResource::class);
        $this->fields['shippingMethodUuid'] = (new FkField('shipping_method_uuid', ShippingMethodWriteResource::class, 'uuid'));
        $this->fields['areaCountry'] = new ReferenceField('areaCountryUuid', 'uuid', AreaCountryWriteResource::class);
        $this->fields['areaCountryUuid'] = (new FkField('area_country_uuid', AreaCountryWriteResource::class, 'uuid'));
        $this->fields['parent'] = new SubresourceField(self::class);
        $this->fields['currencies'] = new SubresourceField(ShopCurrencyWriteResource::class);
        $this->fields['pageGroupMappings'] = new SubresourceField(ShopPageGroupMappingWriteResource::class);
        $this->fields['templateConfigFormFieldValues'] = new SubresourceField(ShopTemplateConfigFormFieldValueWriteResource::class);
        $this->fields['snippets'] = new SubresourceField(SnippetWriteResource::class);
        $this->fields['statisticProductImpressions'] = new SubresourceField(StatisticProductImpressionWriteResource::class);
        $this->fields['statisticSearchs'] = new SubresourceField(StatisticSearchWriteResource::class);
        $this->fields['statisticVisitors'] = new SubresourceField(StatisticVisitorWriteResource::class);
    }

    public function getWriteOrder(): array
    {
        return [
            ConfigFormFieldValueWriteResource::class,
            CustomerWriteResource::class,
            MailAttachmentWriteResource::class,
            OrderWriteResource::class,
            PaymentMethodShopWriteResource::class,
            PremiumProductWriteResource::class,
            ProductCategorySeoWriteResource::class,
            ProductVoteWriteResource::class,
            ShippingMethodWriteResource::class,
            self::class,
            ShopTemplateWriteResource::class,
            CategoryWriteResource::class,
            LocaleWriteResource::class,
            CurrencyWriteResource::class,
            CustomerGroupWriteResource::class,
            PaymentMethodWriteResource::class,
            AreaCountryWriteResource::class,
            ShopCurrencyWriteResource::class,
            ShopPageGroupMappingWriteResource::class,
            ShopTemplateConfigFormFieldValueWriteResource::class,
            SnippetWriteResource::class,
            StatisticProductImpressionWriteResource::class,
            StatisticSearchWriteResource::class,
            StatisticVisitorWriteResource::class,
        ];
    }

    public static function createWrittenEvent(array $updates, TranslationContext $context, array $rawData = [], array $errors = []): ShopWrittenEvent
    {
        $event = new ShopWrittenEvent($updates[self::class] ?? [], $context, $rawData, $errors);

        unset($updates[self::class]);

        /**
         * @var WriteResource
         * @var string[]      $identifiers
         */
        foreach ($updates as $class => $identifiers) {
            $event->addEvent($class::createWrittenEvent($updates, $context));
        }

        return $event;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupRegistrationSalesChannel\CustomerGroupRegistrationSalesChannelDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerWishlist\CustomerWishlistDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Cms\CmsPageDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageSalesChannel\LandingPageSalesChannelDefinition;
use Shopware\Core\Content\LandingPage\LandingPageDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\ProductExport\ProductExportDefinition;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\JsonField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ListField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyIdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslatedField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\TranslationsAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\Country\CountryDefinition;
use Shopware\Core\System\Currency\CurrencyDefinition;
use Shopware\Core\System\Language\LanguageDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeSalesChannel\NumberRangeSalesChannelDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCountry\SalesChannelCountryDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelCurrency\SalesChannelCurrencyDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelPaymentMethod\SalesChannelPaymentMethodDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelShippingMethod\SalesChannelShippingMethodDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelType\SalesChannelTypeDefinition;
use Shopware\Core\System\SystemConfig\SystemConfigDefinition;

#[Package('sales-channel')]
class SalesChannelDefinition extends EntityDefinition
{
    final public const ENTITY_NAME = 'sales_channel';
    final public const CALCULATION_TYPE_VERTICAL = 'vertical';
    final public const CALCULATION_TYPE_HORIZONTAL = 'horizontal';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return SalesChannelCollection::class;
    }

    public function getEntityClass(): string
    {
        return SalesChannelEntity::class;
    }

    public function getDefaults(): array
    {
        return [
            'taxCalculationType' => self::CALCULATION_TYPE_HORIZONTAL,
            'homeEnabled' => true,
        ];
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new FkField('type_id', 'typeId', SalesChannelTypeDefinition::class))->addFlags(new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('customer_group_id', 'customerGroupId', CustomerGroupDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('analytics_id', 'analyticsId', SalesChannelAnalyticsDefinition::class)),

            (new FkField('navigation_category_id', 'navigationCategoryId', CategoryDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new ReferenceVersionField(CategoryDefinition::class, 'navigation_category_version_id'))->addFlags(new ApiAware(), new Required()),
            (new IntField('navigation_category_depth', 'navigationCategoryDepth', 1))->addFlags(new ApiAware()),
            (new FkField('footer_category_id', 'footerCategoryId', CategoryDefinition::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(CategoryDefinition::class, 'footer_category_version_id'))->addFlags(new ApiAware(), new Required()),
            (new FkField('service_category_id', 'serviceCategoryId', CategoryDefinition::class))->addFlags(new ApiAware()),
            (new ReferenceVersionField(CategoryDefinition::class, 'service_category_version_id'))->addFlags(new ApiAware(), new Required()),
            (new FkField('mail_header_footer_id', 'mailHeaderFooterId', MailHeaderFooterDefinition::class))->addFlags(new ApiAware()),
            (new FkField('hreflang_default_domain_id', 'hreflangDefaultDomainId', SalesChannelDomainDefinition::class))->addFlags(new ApiAware()),
            (new TranslatedField('name'))->addFlags(new ApiAware()),
            (new StringField('short_name', 'shortName'))->addFlags(new ApiAware()),
            (new StringField('tax_calculation_type', 'taxCalculationType'))->addFlags(new ApiAware()),
            (new StringField('access_key', 'accessKey'))->addFlags(new Required()),
            (new JsonField('configuration', 'configuration'))->addFlags(new ApiAware()),
            (new BoolField('active', 'active'))->addFlags(new ApiAware()),
            (new BoolField('hreflang_active', 'hreflangActive'))->addFlags(new ApiAware()),
            (new BoolField('maintenance', 'maintenance'))->addFlags(new ApiAware()),
            new ListField('maintenance_ip_whitelist', 'maintenanceIpWhitelist'),
            (new TranslatedField('customFields'))->addFlags(new ApiAware()),
            (new TranslationsAssociationField(SalesChannelTranslationDefinition::class, 'sales_channel_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('currencies', CurrencyDefinition::class, SalesChannelCurrencyDefinition::class, 'sales_channel_id', 'currency_id'),
            new ManyToManyAssociationField('languages', LanguageDefinition::class, SalesChannelLanguageDefinition::class, 'sales_channel_id', 'language_id'),
            new ManyToManyAssociationField('countries', CountryDefinition::class, SalesChannelCountryDefinition::class, 'sales_channel_id', 'country_id'),
            new ManyToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, SalesChannelPaymentMethodDefinition::class, 'sales_channel_id', 'payment_method_id'),
            new ManyToManyIdField('payment_method_ids', 'paymentMethodIds', 'paymentMethods'),
            new ManyToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, SalesChannelShippingMethodDefinition::class, 'sales_channel_id', 'shipping_method_id'),
            new ManyToOneAssociationField('type', 'type_id', SalesChannelTypeDefinition::class, 'id', false),
            (new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('customerGroup', 'customer_group_id', CustomerGroupDefinition::class, 'id', false),
            (new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false))->addFlags(new ApiAware()),
            new OneToManyAssociationField('orders', OrderDefinition::class, 'sales_channel_id', 'id'),

            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'sales_channel_id', 'id')),

            new FkField('home_cms_page_id', 'homeCmsPageId', CmsPageDefinition::class),
            (new ReferenceVersionField(CmsPageDefinition::class, 'home_cms_page_version_id'))->addFlags(new Required()),
            new ManyToOneAssociationField('homeCmsPage', 'home_cms_page_id', CmsPageDefinition::class, 'id', false),
            new TranslatedField('homeSlotConfig'),
            new TranslatedField('homeEnabled'),
            new TranslatedField('homeName'),
            new TranslatedField('homeMetaTitle'),
            new TranslatedField('homeMetaDescription'),
            new TranslatedField('homeKeywords'),

            (new OneToManyAssociationField('domains', SalesChannelDomainDefinition::class, 'sales_channel_id', 'id'))->addFlags(new ApiAware(), new CascadeDelete()),

            (new OneToManyAssociationField('systemConfigs', SystemConfigDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new ManyToOneAssociationField('navigationCategory', 'navigation_category_id', CategoryDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('footerCategory', 'footer_category_id', CategoryDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('serviceCategory', 'service_category_id', CategoryDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new OneToManyAssociationField('productVisibilities', ProductVisibilityDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new OneToOneAssociationField('hreflangDefaultDomain', 'hreflang_default_domain_id', 'id', SalesChannelDomainDefinition::class, false))->addFlags(new ApiAware()),
            new ManyToOneAssociationField('mailHeaderFooter', 'mail_header_footer_id', MailHeaderFooterDefinition::class, 'id', false),
            new OneToManyAssociationField('newsletterRecipients', NewsletterRecipientDefinition::class, 'sales_channel_id', 'id'),
            (new OneToManyAssociationField('numberRangeSalesChannels', NumberRangeSalesChannelDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('promotionSalesChannels', PromotionSalesChannelDefinition::class, 'sales_channel_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('documentBaseConfigSalesChannels', DocumentBaseConfigSalesChannelDefinition::class, 'sales_channel_id', 'id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('productReviews', ProductReviewDefinition::class, 'sales_channel_id', 'id'),
            (new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'sales_channel_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('seoUrlTemplates', SeoUrlTemplateDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productExports', ProductExportDefinition::class, 'sales_channel_id', 'id')),
            (new OneToOneAssociationField('analytics', 'analytics_id', 'id', SalesChannelAnalyticsDefinition::class, true))->addFlags(new CascadeDelete()),
            new ManyToManyAssociationField('customerGroupsRegistrations', CustomerGroupDefinition::class, CustomerGroupRegistrationSalesChannelDefinition::class, 'sales_channel_id', 'customer_group_id', 'id', 'id'),
            new ManyToManyAssociationField('landingPages', LandingPageDefinition::class, LandingPageSalesChannelDefinition::class, 'sales_channel_id', 'landing_page_id', 'id', 'id'),
            new OneToManyAssociationField('boundCustomers', CustomerDefinition::class, 'bound_sales_channel_id', 'id'),
            (new OneToManyAssociationField('wishlists', CustomerWishlistDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
        ]);
    }
}

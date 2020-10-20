<?php declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroup\CustomerGroupDefinition;
use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupRegistrationSalesChannel\CustomerGroupRegistrationSalesChannelDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentBaseConfigSalesChannel\DocumentBaseConfigSalesChannelDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\PaymentMethodDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionSalesChannel\PromotionSalesChannelDefinition;
use Shopware\Core\Checkout\Shipping\ShippingMethodDefinition;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooter\MailHeaderFooterDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateSalesChannel\MailTemplateSalesChannelDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\ProductExport\ProductExportDefinition;
use Shopware\Core\Content\Seo\MainCategory\MainCategoryDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Content\Seo\SeoUrlTemplate\SeoUrlTemplateDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\BoolField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
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
use Shopware\Core\Framework\Event\EventAction\Aggregate\EventActionSalesChannel\EventActionSalesChannelDefinition;
use Shopware\Core\Framework\Event\EventAction\EventActionDefinition;
use Shopware\Core\Framework\Feature;
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

class SalesChannelDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'sales_channel';
    public const CALCULATION_TYPE_VERTICAL = 'vertical';
    public const CALCULATION_TYPE_HORIZONTAL = 'horizontal';

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
        ];
    }

    protected function defineFields(): FieldCollection
    {
        $fields = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            (new FkField('type_id', 'typeId', SalesChannelTypeDefinition::class))->addFlags(new Required()),
            (new FkField('language_id', 'languageId', LanguageDefinition::class))->addFlags(new Required()),
            (new FkField('customer_group_id', 'customerGroupId', CustomerGroupDefinition::class))->addFlags(new Required()),
            (new FkField('currency_id', 'currencyId', CurrencyDefinition::class))->addFlags(new Required()),
            (new FkField('payment_method_id', 'paymentMethodId', PaymentMethodDefinition::class))->addFlags(new Required()),
            (new FkField('shipping_method_id', 'shippingMethodId', ShippingMethodDefinition::class))->addFlags(new Required()),
            (new FkField('country_id', 'countryId', CountryDefinition::class))->addFlags(new Required()),
            (new FkField('analytics_id', 'analyticsId', SalesChannelAnalyticsDefinition::class)),

            (new FkField('navigation_category_id', 'navigationCategoryId', CategoryDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(CategoryDefinition::class, 'navigation_category_version_id'))->addFlags(new Required()),
            new IntField('navigation_category_depth', 'navigationCategoryDepth', 1),

            new FkField('footer_category_id', 'footerCategoryId', CategoryDefinition::class),
            new ReferenceVersionField(CategoryDefinition::class, 'footer_category_version_id'),

            new FkField('service_category_id', 'serviceCategoryId', CategoryDefinition::class),
            new ReferenceVersionField(CategoryDefinition::class, 'service_category_version_id'),

            new FkField('mail_header_footer_id', 'mailHeaderFooterId', MailHeaderFooterDefinition::class),

            new FkField('hreflang_default_domain_id', 'hreflangDefaultDomainId', SalesChannelDomainDefinition::class),

            new TranslatedField('name'),
            new StringField('short_name', 'shortName'),
            new StringField('tax_calculation_type', 'taxCalculationType'),
            (new StringField('access_key', 'accessKey'))->addFlags(new Required()),
            new JsonField('configuration', 'configuration'),
            new BoolField('active', 'active'),
            new BoolField('hreflang_active', 'hreflangActive'),
            new BoolField('maintenance', 'maintenance'),
            new ListField('maintenance_ip_whitelist', 'maintenanceIpWhitelist'),
            new TranslatedField('customFields'),
            (new TranslationsAssociationField(SalesChannelTranslationDefinition::class, 'sales_channel_id'))->addFlags(new Required()),
            new ManyToManyAssociationField('currencies', CurrencyDefinition::class, SalesChannelCurrencyDefinition::class, 'sales_channel_id', 'currency_id'),
            new ManyToManyAssociationField('languages', LanguageDefinition::class, SalesChannelLanguageDefinition::class, 'sales_channel_id', 'language_id'),
            new ManyToManyAssociationField('countries', CountryDefinition::class, SalesChannelCountryDefinition::class, 'sales_channel_id', 'country_id'),
            new ManyToManyAssociationField('paymentMethods', PaymentMethodDefinition::class, SalesChannelPaymentMethodDefinition::class, 'sales_channel_id', 'payment_method_id'),
            new ManyToManyIdField('payment_method_ids', 'paymentMethodIds', 'paymentMethods'),
            new ManyToManyAssociationField('shippingMethods', ShippingMethodDefinition::class, SalesChannelShippingMethodDefinition::class, 'sales_channel_id', 'shipping_method_id'),
            new ManyToOneAssociationField('type', 'type_id', SalesChannelTypeDefinition::class, 'id', false),
            new ManyToOneAssociationField('language', 'language_id', LanguageDefinition::class, 'id', false),
            new ManyToOneAssociationField('customerGroup', 'customer_group_id', CustomerGroupDefinition::class, 'id', false),
            new ManyToOneAssociationField('currency', 'currency_id', CurrencyDefinition::class, 'id', false),
            new ManyToOneAssociationField('paymentMethod', 'payment_method_id', PaymentMethodDefinition::class, 'id', false),
            new ManyToOneAssociationField('shippingMethod', 'shipping_method_id', ShippingMethodDefinition::class, 'id', false),
            new ManyToOneAssociationField('country', 'country_id', CountryDefinition::class, 'id', false),
            new OneToManyAssociationField('orders', OrderDefinition::class, 'sales_channel_id', 'id'),

            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'sales_channel_id', 'id'))
                ->addFlags(new ReadProtected(SalesChannelApiSource::class)),

            (new OneToManyAssociationField('domains', SalesChannelDomainDefinition::class, 'sales_channel_id', 'id'))
                ->addFlags(new CascadeDelete()),

            (new OneToManyAssociationField('systemConfigs', SystemConfigDefinition::class, 'sales_channel_id'))
                ->addFlags(new CascadeDelete()),

            new ManyToOneAssociationField('navigationCategory', 'navigation_category_id', CategoryDefinition::class, 'id', false),
            new ManyToOneAssociationField('footerCategory', 'footer_category_id', CategoryDefinition::class, 'id', false),
            new ManyToOneAssociationField('serviceCategory', 'service_category_id', CategoryDefinition::class, 'id', false),
            (new OneToManyAssociationField('productVisibilities', ProductVisibilityDefinition::class, 'sales_channel_id'))
                ->addFlags(new CascadeDelete()),

            new OneToOneAssociationField('hreflangDefaultDomain', 'hreflang_default_domain_id', 'id', SalesChannelDomainDefinition::class, false),
            new ManyToOneAssociationField('mailHeaderFooter', 'mail_header_footer_id', MailHeaderFooterDefinition::class, 'id', false),
            new OneToManyAssociationField('newsletterRecipients', NewsletterRecipientDefinition::class, 'sales_channel_id', 'id'),
            (new OneToManyAssociationField('mailTemplates', MailTemplateSalesChannelDefinition::class, 'sales_channel_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('numberRangeSalesChannels', NumberRangeSalesChannelDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('promotionSalesChannels', PromotionSalesChannelDefinition::class, 'sales_channel_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('documentBaseConfigSalesChannels', DocumentBaseConfigSalesChannelDefinition::class, 'sales_channel_id', 'id'))->addFlags(new CascadeDelete()),
            new OneToManyAssociationField('productReviews', ProductReviewDefinition::class, 'sales_channel_id', 'id'),
            (new OneToManyAssociationField('seoUrls', SeoUrlDefinition::class, 'sales_channel_id', 'id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('seoUrlTemplates', SeoUrlTemplateDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mainCategories', MainCategoryDefinition::class, 'sales_channel_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productExports', ProductExportDefinition::class, 'sales_channel_id', 'id'))->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new OneToOneAssociationField('analytics', 'analytics_id', 'id', SalesChannelAnalyticsDefinition::class, true))->addFlags(new CascadeDelete()),
            new ManyToManyAssociationField('customerGroupsRegistrations', CustomerGroupDefinition::class, CustomerGroupRegistrationSalesChannelDefinition::class, 'sales_channel_id', 'customer_group_id', 'id', 'id'),
            (new ManyToManyAssociationField('eventActions', EventActionDefinition::class, EventActionSalesChannelDefinition::class, 'sales_channel_id', 'event_action_id'))
                ->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
        ]);

        if (Feature::isActive('FEATURE_NEXT_10555')) {
            $fields->add(
                (new OneToManyAssociationField('boundCustomers', CustomerDefinition::class, 'bound_sales_channel_id', 'id'))
                    ->addFlags(new ReadProtected(SalesChannelApiSource::class))
            );
        }

        return $fields;
    }
}

<?php declare(strict_types=1);

namespace Shopware\Core\System\Language;

use Shopware\Core\Checkout\Customer\Aggregate\CustomerGroupTranslation\CustomerGroupTranslationDefinition;
use Shopware\Core\Checkout\Customer\CustomerDefinition;
use Shopware\Core\Checkout\Document\Aggregate\DocumentTypeTranslation\DocumentTypeTranslationDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Checkout\Payment\Aggregate\PaymentMethodTranslation\PaymentMethodTranslationDefinition;
use Shopware\Core\Checkout\Promotion\Aggregate\PromotionTranslation\PromotionTranslationDefinition;
use Shopware\Core\Checkout\Shipping\Aggregate\ShippingMethodTranslation\ShippingMethodTranslationDefinition;
use Shopware\Core\Content\Category\Aggregate\CategoryTranslation\CategoryTranslationDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsPageTranslation\CmsPageTranslationDefinition;
use Shopware\Core\Content\Cms\Aggregate\CmsSlotTranslation\CmsSlotTranslationDefinition;
use Shopware\Core\Content\ImportExport\ImportExportProfileTranslationDefinition;
use Shopware\Core\Content\LandingPage\Aggregate\LandingPageTranslation\LandingPageTranslationDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailHeaderFooterTranslation\MailHeaderFooterTranslationDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTranslation\MailTemplateTranslationDefinition;
use Shopware\Core\Content\MailTemplate\Aggregate\MailTemplateTypeTranslation\MailTemplateTypeTranslationDefinition;
use Shopware\Core\Content\Media\Aggregate\MediaTranslation\MediaTranslationDefinition;
use Shopware\Core\Content\Newsletter\Aggregate\NewsletterRecipient\NewsletterRecipientDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductCrossSellingTranslation\ProductCrossSellingTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductFeatureSetTranslation\ProductFeatureSetTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductKeywordDictionary\ProductKeywordDictionaryDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturerTranslation\ProductManufacturerTranslationDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchConfig\ProductSearchConfigDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingTranslationDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Framework\App\Aggregate\ActionButtonTranslation\ActionButtonTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\AppTranslation\AppTranslationDefinition;
use Shopware\Core\Framework\App\Aggregate\CmsBlockTranslation\AppCmsBlockTranslationDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ApiAware;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Plugin\Aggregate\PluginTranslation\PluginTranslationDefinition;
use Shopware\Core\System\Country\Aggregate\CountryStateTranslation\CountryStateTranslationDefinition;
use Shopware\Core\System\Country\Aggregate\CountryTranslation\CountryTranslationDefinition;
use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\CurrencyTranslationDefinition;
use Shopware\Core\System\DeliveryTime\Aggregate\DeliveryTimeTranslation\DeliveryTimeTranslationDefinition;
use Shopware\Core\System\Locale\Aggregate\LocaleTranslation\LocaleTranslationDefinition;
use Shopware\Core\System\Locale\LocaleDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeTranslation\NumberRangeTranslationDefinition;
use Shopware\Core\System\NumberRange\Aggregate\NumberRangeTypeTranslation\NumberRangeTypeTranslationDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelLanguage\SalesChannelLanguageDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTranslation\SalesChannelTranslationDefinition;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelTypeTranslation\SalesChannelTypeTranslationDefinition;
use Shopware\Core\System\SalesChannel\SalesChannelDefinition;
use Shopware\Core\System\Salutation\Aggregate\SalutationTranslation\SalutationTranslationDefinition;
use Shopware\Core\System\StateMachine\Aggregation\StateMachineState\StateMachineStateTranslationDefinition;
use Shopware\Core\System\StateMachine\StateMachineTranslationDefinition;
use Shopware\Core\System\Tax\Aggregate\TaxRuleTypeTranslation\TaxRuleTypeTranslationDefinition;
use Shopware\Core\System\Unit\Aggregate\UnitTranslation\UnitTranslationDefinition;

class LanguageDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'language';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getCollectionClass(): string
    {
        return LanguageCollection::class;
    }

    public function getEntityClass(): string
    {
        return LanguageEntity::class;
    }

    public function since(): ?string
    {
        return '6.0.0.0';
    }

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new ApiAware(), new PrimaryKey(), new Required()),
            (new ParentFkField(self::class))->addFlags(new ApiAware()),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->addFlags(new ApiAware(), new Required()),
            (new FkField('translation_code_id', 'translationCodeId', LocaleDefinition::class))->addFlags(new ApiAware()),

            (new StringField('name', 'name'))->addFlags(new ApiAware(), new Required()),
            (new CustomFields())->addFlags(new ApiAware()),
            (new ParentAssociationField(self::class, 'id'))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ManyToOneAssociationField('translationCode', 'translation_code_id', LocaleDefinition::class, 'id', false))->addFlags(new ApiAware()),
            (new ChildrenAssociationField(self::class))->addFlags(new ApiAware()),
            (new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelLanguageDefinition::class, 'language_id', 'sales_channel_id')),

            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'language_id', 'id')),
            (new OneToManyAssociationField('salesChannelDomains', SalesChannelDomainDefinition::class, 'language_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'language_id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('newsletterRecipients', NewsletterRecipientDefinition::class, 'language_id', 'id'))->addFlags(new RestrictDelete()),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'language_id', 'id'))->addFlags(new RestrictDelete()),

            // Translation Associations, not available over sales-channel-api
            (new OneToManyAssociationField('categoryTranslations', CategoryTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('countryStateTranslations', CountryStateTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('countryTranslations', CountryTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('currencyTranslations', CurrencyTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('customerGroupTranslations', CustomerGroupTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('localeTranslations', LocaleTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mediaTranslations', MediaTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('paymentMethodTranslations', PaymentMethodTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productManufacturerTranslations', ProductManufacturerTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productTranslations', ProductTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('shippingMethodTranslations', ShippingMethodTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('unitTranslations', UnitTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('propertyGroupTranslations', PropertyGroupTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('propertyGroupOptionTranslations', PropertyGroupOptionTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('salesChannelTranslations', SalesChannelTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('salesChannelTypeTranslations', SalesChannelTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('salutationTranslations', SalutationTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('pluginTranslations', PluginTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productStreamTranslations', ProductStreamTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('stateMachineTranslations', StateMachineTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('stateMachineStateTranslations', StateMachineStateTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('cmsPageTranslations', CmsPageTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('cmsSlotTranslations', CmsSlotTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mailTemplateTranslations', MailTemplateTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mailHeaderFooterTranslations', MailHeaderFooterTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('documentTypeTranslations', DocumentTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('numberRangeTypeTranslations', NumberRangeTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('deliveryTimeTranslations', DeliveryTimeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productSearchKeywords', ProductSearchKeywordDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productKeywordDictionaries', ProductKeywordDictionaryDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('mailTemplateTypeTranslations', MailTemplateTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('promotionTranslations', PromotionTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('numberRangeTranslations', NumberRangeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productReviews', ProductReviewDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('seoUrlTranslations', SeoUrlDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('taxRuleTypeTranslations', TaxRuleTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productCrossSellingTranslations', ProductCrossSellingTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('importExportProfileTranslations', ImportExportProfileTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productSortingTranslations', ProductSortingTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('productFeatureSetTranslations', ProductFeatureSetTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('appTranslations', AppTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('actionButtonTranslations', ActionButtonTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            (new OneToManyAssociationField('landingPageTranslations', LandingPageTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
        ]);

        if (Feature::isActive('FEATURE_NEXT_14408')) {
            $collection->add(
                (new OneToManyAssociationField('appCmsBlockTranslations', AppCmsBlockTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete()),
            );
        }

        $collection->add(
            (new OneToOneAssociationField('productSearchConfig', 'id', 'language_id', ProductSearchConfigDefinition::class, false))->addFlags(new CascadeDelete())
        );

        return $collection;
    }
}

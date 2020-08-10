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
use Shopware\Core\Content\Product\Aggregate\ProductSearchKeyword\ProductSearchKeywordDefinition;
use Shopware\Core\Content\Product\Aggregate\ProductTranslation\ProductTranslationDefinition;
use Shopware\Core\Content\ProductStream\Aggregate\ProductStreamTranslation\ProductStreamTranslationDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupOptionTranslation\PropertyGroupOptionTranslationDefinition;
use Shopware\Core\Content\Property\Aggregate\PropertyGroupTranslation\PropertyGroupTranslationDefinition;
use Shopware\Core\Content\Seo\SeoUrl\SeoUrlDefinition;
use Shopware\Core\Framework\Api\Context\SalesChannelApiSource;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ChildrenAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\CustomFields;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\CascadeDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\ReadProtected;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RestrictDelete;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\OneToManyAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ParentFkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;
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

    protected function defineFields(): FieldCollection
    {
        $collection = new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),
            new ParentFkField(self::class),
            (new FkField('locale_id', 'localeId', LocaleDefinition::class))->addFlags(new Required()),
            new FkField('translation_code_id', 'translationCodeId', LocaleDefinition::class),

            (new StringField('name', 'name'))->addFlags(new Required()),
            new CustomFields(),

            new ParentAssociationField(self::class, 'id'),

            new ManyToOneAssociationField('locale', 'locale_id', LocaleDefinition::class, 'id', false),
            new ManyToOneAssociationField('translationCode', 'translation_code_id', LocaleDefinition::class, 'id', false),

            new ChildrenAssociationField(self::class),
            (new ManyToManyAssociationField('salesChannels', SalesChannelDefinition::class, SalesChannelLanguageDefinition::class, 'language_id', 'sales_channel_id'))->addFlags(new ReadProtected(SalesChannelApiSource::class)),

            (new OneToManyAssociationField('salesChannelDefaultAssignments', SalesChannelDefinition::class, 'language_id', 'id'))->addFlags(new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('salesChannelDomains', SalesChannelDomainDefinition::class, 'language_id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('customers', CustomerDefinition::class, 'language_id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('newsletterRecipients', NewsletterRecipientDefinition::class, 'language_id', 'id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('orders', OrderDefinition::class, 'language_id', 'id'))->addFlags(new RestrictDelete(), new ReadProtected(SalesChannelApiSource::class)),

            // Translation Associations, not available over sales-channel-api
            (new OneToManyAssociationField('categoryTranslations', CategoryTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('countryStateTranslations', CountryStateTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('countryTranslations', CountryTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('currencyTranslations', CurrencyTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('customerGroupTranslations', CustomerGroupTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('localeTranslations', LocaleTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('mediaTranslations', MediaTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('paymentMethodTranslations', PaymentMethodTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('productManufacturerTranslations', ProductManufacturerTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('productTranslations', ProductTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('shippingMethodTranslations', ShippingMethodTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('unitTranslations', UnitTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('propertyGroupTranslations', PropertyGroupTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('propertyGroupOptionTranslations', PropertyGroupOptionTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('salesChannelTranslations', SalesChannelTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('salesChannelTypeTranslations', SalesChannelTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('salutationTranslations', SalutationTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('pluginTranslations', PluginTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('productStreamTranslations', ProductStreamTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('stateMachineTranslations', StateMachineTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('stateMachineStateTranslations', StateMachineStateTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('cmsPageTranslations', CmsPageTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('cmsSlotTranslations', CmsSlotTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('mailTemplateTranslations', MailTemplateTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('mailHeaderFooterTranslations', MailHeaderFooterTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('documentTypeTranslations', DocumentTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('numberRangeTypeTranslations', NumberRangeTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('deliveryTimeTranslations', DeliveryTimeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('productSearchKeywords', ProductSearchKeywordDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('productKeywordDictionaries', ProductKeywordDictionaryDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('mailTemplateTypeTranslations', MailTemplateTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('promotionTranslations', PromotionTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('numberRangeTranslations', NumberRangeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('productReviews', ProductReviewDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('seoUrlTranslations', SeoUrlDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('taxRuleTypeTranslations', TaxRuleTypeTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('productCrossSellingTranslations', ProductCrossSellingTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
            (new OneToManyAssociationField('importExportProfileTranslations', ImportExportProfileTranslationDefinition::class, 'import_export_profile_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class)),
        ]);

        $collection->add(
            (new OneToManyAssociationField('productFeatureSetTranslations', ProductFeatureSetTranslationDefinition::class, 'language_id'))->addFlags(new CascadeDelete(), new ReadProtected(SalesChannelApiSource::class))
        );

        return $collection;
    }
}

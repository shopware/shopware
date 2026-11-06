/**
 * @sw-package framework
 */

import importExportProfileLabel from './import-export-profile-label';
import mailTemplatePreviewFooter from './mail-template-preview-footer';
import newsletterRecipientFilter from './newsletter-recipient-filter';
import renamedAdminBlocks from './renamed-admin-blocks';
import swBulkEditCustomFields from './sw-bulk-edit-custom-fields';
import swBulkEditProduct from './sw-bulk-edit-product';
import swCategoryView from './sw-category-view';
import swCmsElConfigBuyBox from './sw-cms-el-config-buy-box';
import swCmsElConfigCrossSelling from './sw-cms-el-config-cross-selling';
import swCmsElConfigForm from './sw-cms-el-config-form';
import swCmsElConfigImageGallery from './sw-cms-el-config-image-gallery';
import swCmsElConfigImageSlider from './sw-cms-el-config-image-slider';
import swCmsElConfigProductBox from './sw-cms-el-config-product-box';
import swCmsElConfigProductDescriptionReviews from './sw-cms-el-config-product-description-reviews';
import swCmsElConfigProductListing from './sw-cms-el-config-product-listing';
import swCmsElConfigProductSlider from './sw-cms-el-config-product-slider';
import swCmsElConfigText from './sw-cms-el-config-text';
import swCmsLayoutAssignmentModal from './sw-cms-layout-assignment-modal';
import swConditionLineItemInCategory from './sw-condition-line-item-in-category';
import swConditionLineItemPurchasePrice from './sw-condition-line-item-purchase-price';
import swCustomFieldSetRenderer from './sw-custom-field-set-renderer';
import swCustomFieldTranslatedLabels from './sw-custom-field-translated-labels';
import swCustomerDetail from './sw-customer-detail';
import swFlowDetail from './sw-flow-detail';
import swFlowIndex from './sw-flow-index';
import swFlowList from './sw-flow-list';
import swFlowRuleModal from './sw-flow-rule-modal';
import swImportExport from './sw-import-export';
import swImportExportEditProfileModal from './sw-import-export-edit-profile-modal';
import swImportExportNewProfileWizardCsvPage from './sw-import-export-new-profile-wizard-csv-page';
import swImportExportViewExport from './sw-import-export-view-export';
import swImportExportViewImport from './sw-import-export-view-import';
import swIntegrationList from './sw-integration-list';
import swLandingPageView from './sw-landing-page-view';
import swMailHeaderFooterList from './sw-mail-header-footer-list';
import swMailTemplateIndex from './sw-mail-template-index';
import swMailTemplateList from './sw-mail-template-list';
import swManufacturerDetail from './sw-manufacturer-detail';
import swMediaModalFolderSettings from './sw-media-modal-folder-settings';
import swMediaModalV2 from './sw-media-modal-v2';
import swNewsletterRecipientFilterSwitch from './sw-newsletter-recipient-filter-switch';
import swOrderAddressModal from './sw-order-address-modal';
import swOrderCreate from './sw-order-create';
import swOrderCreateAddressModal from './sw-order-create-address-modal';
import swOrderCreateInitialModal from './sw-order-create-initial-modal';
import swOrderCustomerGrid from './sw-order-customer-grid';
import swOrderDetail from './sw-order-detail';
import swOrderDetailDetails from './sw-order-detail-details';
import swOrderLineItemsGridSalesChannel from './sw-order-line-items-grid-sales-channel';
import swOrderNewCustomerModal from './sw-order-new-customer-modal';
import swOrderUserCard from './sw-order-user-card';
import swPopover from './sw-popover';
import swProductCrossSellingAssignment from './sw-product-cross-selling-assignment';
import swProductDetail from './sw-product-detail';
import swProductDetailContextPrices from './sw-product-detail-context-prices';
import swProductDetailCrossSelling from './sw-product-detail-cross-selling';
import swProductModalDelivery from './sw-product-modal-delivery';
import swProductModalVariantGeneration from './sw-product-modal-variant-generation';
import swProductPackagingForm from './sw-product-packaging-form';
import swProductProperties from './sw-product-properties';
import swProductStreamFilter from './sw-product-stream-filter';
import swProfileIndex from './sw-profile-index';
import swPromotionV2Detail from './sw-promotion-v2-detail';
import swPromotionV2EmptyStateHero from './sw-promotion-v2-empty-state-hero';
import swPromotionV2IndividualCodesBehavior from './sw-promotion-v2-individual-codes-behavior';
import swPromotionV2List from './sw-promotion-v2-list';
import swPropertySearch from './sw-property-search';
import swSalesChannelDetail from './sw-sales-channel-detail';
import swSalesChannelDetailBase from './sw-sales-channel-detail-base';
import swSalesChannelDetailProducts from './sw-sales-channel-detail-products';
import swSalesChannelProductsAssignmentDynamicProductGroups from './sw-sales-channel-products-assignment-dynamic-product-groups';
import swSalesChannelProductsAssignmentModal from './sw-sales-channel-products-assignment-modal';
import swSettingsCountryDetail from './sw-settings-country-detail';
import swSettingsCustomerGroupDetail from './sw-settings-customer-group-detail';
import swSettingsLanguageDetail from './sw-settings-language-detail';
import swSettingsLanguageList from './sw-settings-language-list';
import swSettingsListing from './sw-settings-listing';
import swSettingsListingOptionCriteriaGrid from './sw-settings-listing-option-criteria-grid';
import swSettingsLoggingEntryInfo from './sw-settings-logging-entry-info';
import swSettingsLoggingMailSentInfo from './sw-settings-logging-mail-sent-info';
import swSettingsMailer from './sw-settings-mailer';
import swSettingsProductFeatureSetsValuesCard from './sw-settings-product-feature-sets-values-card';
import swSettingsRuleDetail from './sw-settings-rule-detail';
import swSettingsSearch from './sw-settings-search';
import swSettingsSearchExcludedSearchTerms from './sw-settings-search-excluded-search-terms';
import swSettingsSearchLiveSearch from './sw-settings-search-live-search';
import swSettingsSearchSearchIndex from './sw-settings-search-search-index';
import swSettingsSearchSearchableContent from './sw-settings-search-searchable-content';
import swSettingsSearchSearchableContentCustomfields from './sw-settings-search-searchable-content-customfields';
import swSettingsSearchSearchableContentGeneral from './sw-settings-search-searchable-content-general';
import swSettingsSnippetSetList from './sw-settings-snippet-set-list';
import swSettingsStorefrontConfiguration from './sw-settings-storefront-configuration';
import swSettingsStorefrontIndex from './sw-settings-storefront-index';
import swSettingsTagDetailModal from './sw-settings-tag-detail-modal';
import swTaxRuleCard from './sw-tax-rule-card';
import swThemeManagerDetail from './sw-theme-manager-detail';
import swUsersPermissionsUserListing from './sw-users-permissions-user-listing';
import type { TemplateBlockMigration } from '../helpers';

export default [
    importExportProfileLabel,
    mailTemplatePreviewFooter,
    newsletterRecipientFilter,
    renamedAdminBlocks,
    swBulkEditCustomFields,
    swBulkEditProduct,
    swCategoryView,
    swCmsElConfigBuyBox,
    swCmsElConfigCrossSelling,
    swCmsElConfigForm,
    swCmsElConfigImageGallery,
    swCmsElConfigImageSlider,
    swCmsElConfigProductBox,
    swCmsElConfigProductDescriptionReviews,
    swCmsElConfigProductListing,
    swCmsElConfigProductSlider,
    swCmsElConfigText,
    swCmsLayoutAssignmentModal,
    swConditionLineItemInCategory,
    swConditionLineItemPurchasePrice,
    swCustomFieldSetRenderer,
    swCustomFieldTranslatedLabels,
    swCustomerDetail,
    swFlowDetail,
    swFlowIndex,
    swFlowList,
    swFlowRuleModal,
    swImportExport,
    swImportExportEditProfileModal,
    swImportExportNewProfileWizardCsvPage,
    swImportExportViewExport,
    swImportExportViewImport,
    swIntegrationList,
    swLandingPageView,
    swMailHeaderFooterList,
    swMailTemplateIndex,
    swMailTemplateList,
    swManufacturerDetail,
    swMediaModalFolderSettings,
    swMediaModalV2,
    swNewsletterRecipientFilterSwitch,
    swOrderAddressModal,
    swOrderCreate,
    swOrderCreateAddressModal,
    swOrderCreateInitialModal,
    swOrderCustomerGrid,
    swOrderDetail,
    swOrderDetailDetails,
    swOrderLineItemsGridSalesChannel,
    swOrderNewCustomerModal,
    swOrderUserCard,
    swPopover,
    swProductCrossSellingAssignment,
    swProductDetail,
    swProductDetailContextPrices,
    swProductDetailCrossSelling,
    swProductModalDelivery,
    swProductModalVariantGeneration,
    swProductPackagingForm,
    swProductProperties,
    swProductStreamFilter,
    swProfileIndex,
    swPromotionV2Detail,
    swPromotionV2EmptyStateHero,
    swPromotionV2IndividualCodesBehavior,
    swPromotionV2List,
    swPropertySearch,
    swSalesChannelDetail,
    swSalesChannelDetailBase,
    swSalesChannelDetailProducts,
    swSalesChannelProductsAssignmentDynamicProductGroups,
    swSalesChannelProductsAssignmentModal,
    swSettingsCountryDetail,
    swSettingsCustomerGroupDetail,
    swSettingsLanguageDetail,
    swSettingsLanguageList,
    swSettingsListing,
    swSettingsListingOptionCriteriaGrid,
    swSettingsLoggingEntryInfo,
    swSettingsLoggingMailSentInfo,
    swSettingsMailer,
    swSettingsProductFeatureSetsValuesCard,
    swSettingsRuleDetail,
    swSettingsSearch,
    swSettingsSearchExcludedSearchTerms,
    swSettingsSearchLiveSearch,
    swSettingsSearchSearchIndex,
    swSettingsSearchSearchableContent,
    swSettingsSearchSearchableContentCustomfields,
    swSettingsSearchSearchableContentGeneral,
    swSettingsSnippetSetList,
    swSettingsStorefrontConfiguration,
    swSettingsStorefrontIndex,
    swSettingsTagDetailModal,
    swTaxRuleCard,
    swThemeManagerDetail,
    swUsersPermissionsUserListing,
] satisfies TemplateBlockMigration[];

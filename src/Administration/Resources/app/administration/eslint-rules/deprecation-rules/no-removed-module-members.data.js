/**
 * @sw-package framework
 *
 * The remaining members of the settings, product and promotion modules that 6.8 removes, taken from the
 * `@deprecated tag:v6.8.0` markers. Generic names are only marked in overrides of their component; `unique` names
 * are distinctive and exist in one core component only. Members covered by other codemods are left out: product
 * states (`product-states`), `sw-system-config` (`system-config-schema`), the CMS helpers (`cms-helpers`), the
 * filter computeds (`filter-computeds`), the mail templates (`mail-template-api`) and all Twig blocks.
 */

const NONE = 'is removed without replacement.';

module.exports = {
    id: 'settings-product-promotion-members',
    description: 'Marks removed members of the settings, product and promotion modules',
    components: {
        'sw-settings-services-dashboard-banner': 'sw-settings-services-dashboard-banner is removed without replacement. The services banner is no longer shown on the dashboard.',
        'sw-promotion-v2-empty-state-hero': 'sw-promotion-v2-empty-state-hero is removed without replacement. The promotion list uses mt-empty-state.',
    },
    members: [
        // Settings
        { name: 'hideSettingRenameBanner', unique: true, message: `sw-settings-index.hideSettingRenameBanner ${NONE} The rename notice is a dismissible notice from changeNotices now.` },
        { name: 'onCloseSettingRenameBanner', unique: true, message: `sw-settings-index.onCloseSettingRenameBanner() ${NONE} The rename notice is a dismissible notice from changeNotices now.` },
        { component: 'sw-settings-index', name: 'getUserConfig', message: `sw-settings-index.getUserConfig() ${NONE} It only loaded the hidden state of the rename banner.` },
        { component: 'sw-settings-customer-group-detail', name: 'seoUrlRepository', message: `sw-settings-customer-group-detail.seoUrlRepository ${NONE} SEO URLs are built in the template from salesChannel.seoUrls and domain.url.` },
        { component: 'sw-settings-customer-group-detail', name: 'seoUrlCriteria', message: `sw-settings-customer-group-detail.seoUrlCriteria ${NONE} SEO URLs are built in the template from salesChannel.seoUrls and domain.url.` },
        { component: 'sw-settings-customer-group-detail', name: 'loadSeoUrls', message: `sw-settings-customer-group-detail.loadSeoUrls() ${NONE} SEO URLs are built in the template from salesChannel.seoUrls and domain.url.` },
        { component: 'sw-settings-customer-group-detail', name: 'getSeoUrl', message: `sw-settings-customer-group-detail.getSeoUrl() ${NONE} SEO URLs are built in the template from salesChannel.seoUrls and domain.url.` },
        { name: 'shippingRuleFilterCriteria', unique: true, message: 'sw-settings-shipping-price-matrix.shippingRuleFilterCriteria is removed. Use ruleFilterCriteria.' },
        { name: 'selectedNumberRangeSalesChannels', unique: true, message: 'sw-settings-number-range-detail.selectedNumberRangeSalesChannels is removed. Use the selectedSalesChannelsCollection data.' },
        { component: 'sw-settings-number-range-detail', name: 'loadSalesChannels', message: 'sw-settings-number-range-detail.loadSalesChannels() is removed. Use buildSelectedSalesChannelsCollection().' },
        { name: 'productSortingEntityCriteria', unique: true, message: `sw-settings-listing-option-base.productSortingEntityCriteria ${NONE} It was not used.` },
        { name: 'customFieldSetIDs', unique: true, message: `sw-settings-listing-option-criteria-grid.customFieldSetIDs ${NONE} The grid filters custom fields by customFieldSet.relations.entityName directly.` },
        { name: 'customFieldSetRelationsRepository', unique: true, message: `sw-settings-listing-option-criteria-grid.customFieldSetRelationsRepository ${NONE} The grid filters custom fields by customFieldSet.relations.entityName directly.` },
        { name: 'customFieldsRelationsCriteria', unique: true, message: `sw-settings-listing-option-criteria-grid.customFieldsRelationsCriteria ${NONE} The grid filters custom fields by customFieldSet.relations.entityName directly.` },
        { name: 'fetchCustomFieldSetIds', unique: true, message: `sw-settings-listing-option-criteria-grid.fetchCustomFieldSetIds() ${NONE} The grid filters custom fields by customFieldSet.relations.entityName directly.` },
        { component: 'sw-settings-language-list', name: 'filterSidebarItem', message: `sw-settings-language-list.filterSidebarItem ${NONE} The language list has no filter sidebar anymore.` },
        { component: 'sw-settings-language-list', name: 'registerFilterSidebarItem', message: `sw-settings-language-list.registerFilterSidebarItem() ${NONE} The language list has no filter sidebar anymore.` },
        { component: 'sw-settings-language-list', name: 'openFilterSidebar', message: `sw-settings-language-list.openFilterSidebar() ${NONE} The language list has no filter sidebar anymore.` },
        { component: 'sw-settings-document-detail', name: 'selectedType', message: `sw-settings-document-detail.selectedType ${NONE}` },
        { component: 'sw-settings-document-detail', name: 'isSaveSuccessful', message: `sw-settings-document-detail.isSaveSuccessful ${NONE}` },
        { component: 'sw-settings-document-detail', name: 'isShowCountriesSelect', message: `sw-settings-document-detail.isShowCountriesSelect ${NONE}` },
        { component: 'sw-settings-document-detail', name: 'countryRepository', message: `sw-settings-document-detail.countryRepository ${NONE}` },
        { component: 'sw-settings-document-detail', name: 'documentTypeRepository', message: `sw-settings-document-detail.documentTypeRepository ${NONE}` },
        { component: 'sw-settings-document-detail', name: 'documentBaseConfigSalesChannelRepository', message: `sw-settings-document-detail.documentBaseConfigSalesChannelRepository ${NONE}` },
        { component: 'sw-settings-document-detail', name: 'loadAvailableSalesChannel', message: `sw-settings-document-detail.loadAvailableSalesChannel() ${NONE}` },
        { component: 'sw-settings-document-detail', name: 'showOption', message: `sw-settings-document-detail.showOption() ${NONE}` },
        { component: 'sw-settings-document-list', name: 'sortBy', kinds: ['data'], message: `sw-settings-document-list.sortBy ${NONE}` },
        { component: 'sw-settings-document-list', name: 'expandButtonClass', message: `sw-settings-document-list.expandButtonClass ${NONE}` },
        { component: 'sw-settings-document-list', name: 'collapseButtonClass', message: `sw-settings-document-list.collapseButtonClass ${NONE}` },
        { component: 'sw-settings-snippet-set-list', name: 'onClone', message: `sw-settings-snippet-set-list.onClone() ${NONE} The duplicate action calls onConfirmClone(id) directly, without a modal.` },
        { component: 'sw-settings-snippet-set-list', name: 'closeCloneModal', message: `sw-settings-snippet-set-list.closeCloneModal() ${NONE} The duplicate action calls onConfirmClone(id) directly, without a modal.` },
        { component: 'sw-settings-snippet-set-list', name: 'getNoPermissionsTooltip', message: `sw-settings-snippet-set-list.getNoPermissionsTooltip() ${NONE}` },
        { component: 'sw-settings-snippet-list', name: 'inlineSaveSuccessMessage', message: `sw-settings-snippet-list.inlineSaveSuccessMessage() ${NONE}` },
        { component: 'sw-settings-snippet-list', name: 'createSuccessMessage', message: `sw-settings-snippet-list.createSuccessMessage() ${NONE}` },
        { component: 'sw-settings-cache-index', name: 'processSuccess', message: `sw-settings-cache-index.processSuccess ${NONE}` },
        { component: 'sw-settings-cache-index', name: 'resetButtons', message: `sw-settings-cache-index.resetButtons() ${NONE}` },
        // Product
        { component: 'sw-product-cross-selling-form', name: 'productCrossSellingRepository', message: `sw-product-cross-selling-form.productCrossSellingRepository ${NONE} It was not used.` },
        { name: 'crossSellingAssigmentRepository', unique: true, message: `sw-product-cross-selling-form.crossSellingAssigmentRepository ${NONE} It was not used.` },
        { component: 'sw-product-cross-selling-form', name: 'disablePositioning', message: `sw-product-cross-selling-form.disablePositioning ${NONE} It was not used.` },
        { component: ['sw-product-clone-modal', 'sw-product-modal-variant-generation'], name: 'progressInPercentage', message: `progressInPercentage of sw-product-clone-modal and sw-product-modal-variant-generation ${NONE}` },
        { component: 'sw-product-packaging-form', name: 'isLoading', message: `sw-product-packaging-form.isLoading ${NONE} It was not used.` },
        { name: 'loadConfigSettingGroups', unique: true, message: `sw-product-detail-variants.loadConfigSettingGroups() ${NONE}` },
        { component: ['sw-product-detail-base', 'sw-bulk-edit-product-media'], name: 'mediaDefaultFolderRepository', message: "mediaDefaultFolderRepository is removed. Use Shopware.Service('mediaDefaultFolderService').getDefaultFolderId('product')." },
        { component: ['sw-product-detail-base', 'sw-bulk-edit-product-media'], name: 'mediaDefaultFolderCriteria', message: "mediaDefaultFolderCriteria is removed. Use Shopware.Service('mediaDefaultFolderService').getDefaultFolderId('product')." },
        // Promotion
        { component: ['sw-promotion-v2-cart-condition-form', 'sw-promotion-discount-component'], name: 'ruleFilter', kinds: ['computed'], message: 'This ruleFilter computed is removed. It equals the default ruleFilter of sw-select-rule-create; drop the :rule-filter binding.' },
        { component: ['sw-promotion-v2-settings-rule-selection', 'sw-promotion-v2-settings-trigger'], name: 'ruleCriteria', message: 'This ruleCriteria computed is removed. It equals the default ruleFilter of sw-select-rule-create; drop the :rule-filter binding.' },
        { name: 'personaRuleFilter', unique: true, message: 'sw-promotion-v2-conditions.personaRuleFilter is removed. It equals the default ruleFilter of sw-select-rule-create; drop the :rule-filter binding.' },
        { name: 'cartConditionsRuleFilter', unique: true, message: 'sw-promotion-v2-conditions.cartConditionsRuleFilter is removed. It equals the default ruleFilter of sw-select-rule-create; drop the :rule-filter binding.' },
        { name: 'orderConditionsFilter', unique: true, message: 'sw-promotion-v2-conditions.orderConditionsFilter is removed. It equals the default ruleFilter of sw-select-rule-create; drop the :rule-filter binding.' },
        { name: 'maxValueAdvancedPricesTooltip', unique: true, message: `sw-promotion-discount-component.maxValueAdvancedPricesTooltip ${NONE}` },
        { component: 'sw-promotion-detail-discounts', name: 'deleteDiscountId', kinds: ['data'], message: `sw-promotion-detail-discounts.deleteDiscountId ${NONE}` },
        { component: 'sw-promotion-detail-discounts', name: 'repository', kinds: ['data'], message: `sw-promotion-detail-discounts.repository ${NONE}` },
    ],
    literals: [
        { value: 'settings.hideRenameBanner', message: `The user config key settings.hideRenameBanner ${NONE} The rename notice is a dismissible notice from changeNotices now.` },
    ],
};

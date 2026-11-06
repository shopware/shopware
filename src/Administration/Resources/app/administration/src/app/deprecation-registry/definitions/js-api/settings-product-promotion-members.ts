/**
 * @sw-package framework
 */

import { componentMembers, componentReference, jsApiMigration, upgradeGuide } from '../helpers';

export default jsApiMigration({
    id: 'js-api.settings-product-promotion-members',
    deprecatedIn: '6.7.0',
    removedIn: '6.8.0',
    description: 'These members of settings, product and promotion components are removed.',
    references: [upgradeGuide('UPGRADE-6.8.md#removed-settings-product-and-promotion-members')],
    usage: [
        ...componentMembers({
            components: ['sw-settings-index'],
            members: ['hideSettingRenameBanner', 'getUserConfig', 'onCloseSettingRenameBanner'],
        }),
        ...componentMembers({
            components: ['sw-settings-customer-group-detail'],
            members: [
                'seoUrlRepository',
                'seoUrlCriteria',
                'loadSeoUrls',
                'getSeoUrl',
            ],
        }),
        ...componentMembers({
            components: ['sw-settings-number-range-detail'],
            members: ['selectedNumberRangeSalesChannels'],
            to: 'selectedSalesChannelsCollection',
        }),
        ...componentMembers({
            components: ['sw-settings-number-range-detail'],
            members: ['loadSalesChannels'],
            to: 'buildSelectedSalesChannelsCollection()',
        }),
        ...componentMembers({
            components: ['sw-settings-shipping-price-matrix'],
            members: ['shippingRuleFilterCriteria'],
            to: 'ruleFilterCriteria',
        }),
        ...componentMembers({ components: ['sw-settings-listing-option-base'], members: ['productSortingEntityCriteria'] }),
        ...componentMembers({
            components: ['sw-settings-language-list'],
            members: ['filterSidebarItem', 'registerFilterSidebarItem', 'openFilterSidebar'],
        }),
        ...componentMembers({
            components: ['sw-settings-snippet-set-list'],
            members: ['onClone', 'closeCloneModal', 'getNoPermissionsTooltip'],
        }),
        ...componentMembers({
            components: ['sw-settings-snippet-list'],
            members: ['inlineSaveSuccessMessage', 'createSuccessMessage'],
        }),
        ...componentMembers({ components: ['sw-settings-cache-index'], members: ['processSuccess', 'resetButtons'] }),
        ...componentMembers({
            components: ['sw-settings-document-detail'],
            members: [
                'selectedType',
                'isSaveSuccessful',
                'isShowCountriesSelect',
                'countryRepository',
                'documentTypeRepository',
                'documentBaseConfigSalesChannelRepository',
                'loadAvailableSalesChannel',
                'showOption',
            ],
        }),
        ...componentMembers({
            components: ['sw-settings-document-list'],
            members: ['sortBy', 'expandButtonClass', 'collapseButtonClass'],
        }),
        ...componentMembers({
            components: ['sw-product-cross-selling-form'],
            members: ['productCrossSellingRepository', 'crossSellingAssigmentRepository', 'disablePositioning'],
        }),
        ...componentMembers({
            components: ['sw-product-clone-modal', 'sw-product-modal-variant-generation'],
            members: ['progressInPercentage'],
        }),
        ...componentMembers({ components: ['sw-product-packaging-form'], members: ['isLoading'] }),
        ...componentMembers({ components: ['sw-product-list'], members: ['productTypeOptions'] }),
        ...componentMembers({ components: ['sw-product-detail-variants'], members: ['loadConfigSettingGroups'] }),
        ...componentMembers({
            components: ['sw-promotion-v2-cart-condition-form', 'sw-promotion-discount-component'],
            members: ['ruleFilter'],
            to: 'the default filter of sw-select-rule-create',
        }),
        ...componentMembers({
            components: ['sw-promotion-v2-settings-rule-selection', 'sw-promotion-v2-settings-trigger'],
            members: ['ruleCriteria'],
            to: 'the default filter of sw-select-rule-create',
        }),
        ...componentMembers({
            components: ['sw-promotion-v2-conditions'],
            members: ['personaRuleFilter', 'cartConditionsRuleFilter', 'orderConditionsFilter'],
            to: 'the default filter of sw-select-rule-create',
        }),
        ...componentMembers({ components: ['sw-promotion-discount-component'], members: ['maxValueAdvancedPricesTooltip'] }),
        ...componentMembers({ components: ['sw-promotion-detail-discounts'], members: ['deleteDiscountId', 'repository'] }),
        componentReference({ component: 'sw-settings-services-dashboard-banner' }),
        componentReference({ component: 'sw-promotion-v2-empty-state-hero' }),
    ],
});
